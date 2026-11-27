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

namespace Dialog\AskDialog\Service\OrderLookup;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\ShopperFormat;

/**
 * The summary Dialog tracks an order with: one entry per order of the reference, a split
 * order's parts each with its own state, carrier and lines
 *
 * Field by field, so that nothing else of the order, its customer above all, leaves the store.
 */
class OrderSummaryBuilder
{
    /** @var array<string, int> PS_OS_CANCELED, PS_OS_ERROR and PS_OS_REFUND state ids */
    private $closingStates;

    /**
     * @param array<string, int> $closingStates
     */
    public function __construct(array $closingStates)
    {
        $this->closingStates = $closingStates;
    }

    /**
     * @param array<int, array<string, mixed>> $orders ordered by id_order, never empty
     * @param array<int|string, array<string, mixed>> $carriers keyed by id_order
     * @param array<int|string, array<int, array<string, mixed>>> $history keyed by id_order, oldest first
     * @param array<int|string, array<int, array<string, mixed>>> $lines keyed by id_order
     *
     * @return array<string, mixed>
     */
    public function build(string $reference, array $orders, array $carriers, array $history, array $lines): array
    {
        $parts = [];
        foreach ($orders as $order) {
            $idOrder = (int) $order['id_order'];
            $parts[] = $this->toPart($order, $carriers[$idOrder] ?? null, $history[$idOrder] ?? [], $lines[$idOrder] ?? []);
        }

        return [
            'reference' => $reference,
            'idCustomer' => (int) $orders[0]['id_customer'],
            'createdAt' => ShopperFormat::toIso(min(array_column($orders, 'date_add'))),
            'orders' => $parts,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param array<string, mixed>|null $carrier
     * @param array<int, array<string, mixed>> $history
     * @param array<int, array<string, mixed>> $lines
     *
     * @return array<string, mixed>
     */
    private function toPart(array $order, ?array $carrier, array $history, array $lines): array
    {
        $state = (int) $order['current_state'];
        $canceledStates = [$this->closingStates['PS_OS_CANCELED'], $this->closingStates['PS_OS_ERROR']];

        return [
            'idOrder' => (int) $order['id_order'],
            'createdAt' => ShopperFormat::toIso($order['date_add']),
            'state' => [
                'id' => $state,
                'paid' => (bool) $order['paid'],
                'shipped' => (bool) $order['shipped'],
                'delivered' => (bool) $order['delivered'],
                'canceled' => $state === $this->closingStates['PS_OS_CANCELED'],
                'refund' => $state === $this->closingStates['PS_OS_REFUND'],
                'error' => $state === $this->closingStates['PS_OS_ERROR'],
            ],
            'dates' => [
                'shippedAt' => $this->firstDate($history, function (array $entry): bool {
                    return (bool) $entry['shipped'];
                }),
                'deliveredAt' => $this->firstDate($history, function (array $entry): bool {
                    return (bool) $entry['delivered'];
                }),
                'canceledAt' => $this->firstDate(array_reverse($history), function (array $entry) use ($canceledStates): bool {
                    return in_array((int) $entry['id_order_state'], $canceledStates, true);
                }),
            ],
            'carrier' => $carrier === null ? null : $this->toCarrier($carrier),
            'lines' => array_map(function (array $line): array {
                return [
                    'idProduct' => (int) $line['product_id'],
                    'title' => $line['product_name'],
                    'quantity' => (int) $line['product_quantity'],
                ];
            }, $lines),
        ];
    }

    /**
     * @param array<string, mixed> $carrier
     *
     * @return array{name: string|null, trackingNumber: string|null, trackingUrl: string|null}
     */
    private function toCarrier(array $carrier): array
    {
        $name = self::nonEmpty($carrier['name'] ?? null);
        $trackingNumber = self::nonEmpty($carrier['tracking_number'] ?? null);
        $url = self::nonEmpty($carrier['url'] ?? null);

        return [
            'name' => $name,
            'trackingNumber' => $trackingNumber,
            // PrestaShop's own rule: the carrier's URL with its `@` replaced by the number
            'trackingUrl' => $url === null || $trackingNumber === null
                ? null
                : str_replace('@', rawurlencode($trackingNumber), $url),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $history
     */
    private function firstDate(array $history, callable $matches): ?string
    {
        foreach ($history as $entry) {
            if ($matches($entry)) {
                return ShopperFormat::toIso($entry['date_add']);
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function nonEmpty($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
