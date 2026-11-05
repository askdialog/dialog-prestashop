<?php
/**
 * 2026 Dialog
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Axel Paillaud <contact@axelweb.fr>
 * @copyright 2026 Dialog
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Dialog\AskDialog\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\ShopperFormat;
use Dialog\AskDialog\Repository\ShopperOrderRepository;

/**
 * Reads the orders and credit slips of identified shoppers: from a `(date_upd, id)` cursor
 * for the current flow, and a customer's most recent orders when Dialog first links them
 *
 * A cursor page stops before the current second, which can still receive writes.
 */
class ShopperOrdersReader
{
    public const HISTORY_SIZE = 200;

    /** @var ShopperOrderRepository */
    private $orders;

    /** @var array<int, array<string, int>> PS_OS_* state ids, keyed by shop */
    private $closingStates = [];

    public function __construct()
    {
        $this->orders = new ShopperOrderRepository();
    }

    /**
     * @return array{orders: array<int, array<string, mixed>>, nextCursor: array{updatedAfter: string, afterId: int}}
     */
    public function readChanged(\DateTimeInterface $updatedAfter, int $afterId, int $limit): array
    {
        $after = ShopperFormat::toLocal($updatedAfter);
        $rows = $this->orders->findChangedOrders($after, $afterId, date('Y-m-d H:i:s'), $limit);

        return [
            'orders' => $this->toOrders($rows),
            'nextCursor' => $this->nextCursor($rows, 'id_order', $after, $afterId),
        ];
    }

    /**
     * @return array{orders: array<int, array<string, mixed>>}
     */
    public function readCustomerHistory(int $idCustomer): array
    {
        return ['orders' => $this->toOrders($this->orders->findRecentOrders($idCustomer, self::HISTORY_SIZE))];
    }

    /**
     * @return array{creditSlips: array<int, array<string, mixed>>, nextCursor: array{updatedAfter: string, afterId: int}}
     */
    public function readChangedCreditSlips(\DateTimeInterface $updatedAfter, int $afterId, int $limit): array
    {
        $after = ShopperFormat::toLocal($updatedAfter);
        $rows = $this->orders->findChangedCreditSlips($after, $afterId, date('Y-m-d H:i:s'), $limit);

        $creditSlips = [];
        foreach ($rows as $row) {
            $creditSlips[] = [
                'idOrderSlip' => (int) $row['id_order_slip'],
                'idOrder' => (int) $row['id_order'],
                'amount' => ShopperFormat::toAmount((float) $row['total_products_tax_incl'] + (float) $row['total_shipping_tax_incl']),
                'createdAt' => ShopperFormat::toIso($row['date_add']),
                'updatedAt' => ShopperFormat::toIso($row['date_upd']),
            ];
        }

        return [
            'creditSlips' => $creditSlips,
            'nextCursor' => $this->nextCursor($rows, 'id_order_slip', $after, $afterId),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function toOrders(array $rows): array
    {
        $lines = $this->orders->findLines(array_column($rows, 'id_order'));

        $orders = [];
        foreach ($rows as $row) {
            $closingStates = $this->closingStates((int) $row['id_shop']);
            $state = (int) $row['current_state'];
            $orders[] = [
                'idOrder' => (int) $row['id_order'],
                'reference' => $row['reference'],
                'idCart' => (int) $row['id_cart'],
                'idShop' => (int) $row['id_shop'],
                'idCustomer' => (int) $row['id_customer'],
                'totalPaid' => ShopperFormat::toAmount((float) $row['total_paid']),
                'currency' => \Currency::getIsoCodeById((int) $row['id_currency']),
                'createdAt' => ShopperFormat::toIso($row['date_add']),
                'updatedAt' => ShopperFormat::toIso($row['date_upd']),
                'state' => [
                    'id' => $state,
                    'paid' => (bool) $row['paid'],
                    'canceled' => $state === $closingStates['PS_OS_CANCELED'],
                    'error' => $state === $closingStates['PS_OS_ERROR'],
                    'refund' => $state === $closingStates['PS_OS_REFUND'],
                ],
                'lines' => array_map(function (array $line): array {
                    return [
                        'idProduct' => (int) $line['product_id'],
                        'idProductAttribute' => (int) $line['product_attribute_id'],
                        'title' => $line['product_name'],
                        'quantity' => (int) $line['product_quantity'],
                        'unitPrice' => ShopperFormat::toAmount((float) $line['unit_price_tax_incl']),
                    ];
                }, $lines[$row['id_order']] ?? []),
            ];
        }

        return $orders;
    }

    /**
     * @return array<string, int>
     */
    private function closingStates(int $idShop): array
    {
        if (!isset($this->closingStates[$idShop])) {
            $idShopGroup = (int) \Shop::getGroupFromShop($idShop);
            foreach (['PS_OS_CANCELED', 'PS_OS_ERROR', 'PS_OS_REFUND'] as $key) {
                $this->closingStates[$idShop][$key] = (int) \Configuration::get($key, null, $idShopGroup, $idShop);
            }
        }

        return $this->closingStates[$idShop];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array{updatedAfter: string, afterId: int}
     */
    private function nextCursor(array $rows, string $idColumn, string $after, int $afterId): array
    {
        $last = end($rows);
        if ($last === false) {
            return ['updatedAfter' => ShopperFormat::toIso($after), 'afterId' => $afterId];
        }

        return ['updatedAfter' => ShopperFormat::toIso($last['date_upd']), 'afterId' => (int) $last[$idColumn]];
    }
}
