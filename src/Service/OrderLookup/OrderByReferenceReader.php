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

use Dialog\AskDialog\Repository\OrderByReferenceRepository;
use Dialog\AskDialog\Repository\ShopperOrderRepository;

/**
 * Reads a shop's order by its reference for the shopper who gives its email
 *
 * An unknown reference and another email both read as no order: Dialog learns nothing of an
 * order it was not proven to be the shopper's, and no email leaves the store.
 */
class OrderByReferenceReader
{
    public const REFERENCE_PATTERN = '/^[A-Za-z]{9}$/';

    /** @var OrderByReferenceRepository */
    private $orders;

    /** @var ShopperOrderRepository */
    private $lines;

    public function __construct()
    {
        $this->orders = new OrderByReferenceRepository();
        $this->lines = new ShopperOrderRepository();
    }

    /**
     * @return array{order: array<string, mixed>|null}
     */
    public function read(string $reference, string $email, int $idShop): array
    {
        $reference = strtoupper($reference);
        $orders = $this->orders->findOrders($reference, $idShop);
        if (!OrderOwnership::matches(array_column($orders, 'email'), $email)) {
            return ['order' => null];
        }

        $orderIds = array_map('intval', array_column($orders, 'id_order'));
        $builder = new OrderSummaryBuilder($this->states($idShop));

        return ['order' => $builder->build(
            $reference,
            $orders,
            $this->carriersOf($orderIds),
            $this->orders->findHistory($orderIds),
            $this->lines->findLines($orderIds)
        )];
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function carriersOf(array $orderIds): array
    {
        $carriers = $this->orders->findCarriers($orderIds);
        foreach ($carriers as $idOrder => $carrier) {
            // A carrier named "0" shows as the shop's name, as on PrestaShop's own pages
            if ($carrier['name'] === '0') {
                $carriers[$idOrder]['name'] = \Configuration::get('PS_SHOP_NAME');
            }
        }

        return $carriers;
    }

    /**
     * @return array<string, int>
     */
    private function states(int $idShop): array
    {
        $idShopGroup = (int) \Shop::getGroupFromShop($idShop);
        $states = [];
        foreach (['PS_OS_DELIVERED', 'PS_OS_CANCELED', 'PS_OS_ERROR', 'PS_OS_REFUND'] as $key) {
            $states[$key] = (int) \Configuration::get($key, null, $idShopGroup, $idShop);
        }

        return $states;
    }
}
