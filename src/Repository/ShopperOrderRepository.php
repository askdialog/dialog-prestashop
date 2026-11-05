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

namespace Dialog\AskDialog\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Orders and credit slips of the customers Dialog identified: those with a linked cart
 *
 * `ps_orders` and `order_slip` have no index on `date_upd`: reads start from the linked
 * customers and use the `id_customer` indexes, never a scan of every order.
 */
class ShopperOrderRepository extends AbstractRepository
{
    private const ORDER_COLUMNS = 'o.`id_order`, o.`reference`, o.`id_cart`, o.`id_shop`, o.`id_customer`,'
        . ' o.`id_currency`, o.`total_paid`, o.`current_state`, os.`paid`, o.`date_add`, o.`date_upd`';

    /**
     * @return array<int, array<string, string>>
     */
    public function findChangedOrders(string $after, int $afterId, string $before, int $limit): array
    {
        return $this->selectRows(
            'SELECT ' . self::ORDER_COLUMNS
            . ' FROM (' . $this->linkedCustomersSql() . ') linked'
            . " JOIN `{$this->getPrefix()}orders` o ON o.`id_customer` = linked.`id_customer`"
            . $this->orderStateJoinSql()
            . ' WHERE ' . $this->afterCursorSql('o.`date_upd`', 'o.`id_order`', $after, $afterId, $before)
            . ' ORDER BY o.`date_upd`, o.`id_order` LIMIT ' . $limit
        );
    }

    /**
     * Nothing for a customer without a linked cart
     *
     * @return array<int, array<string, string>>
     */
    public function findRecentOrders(int $idCustomer, int $limit): array
    {
        return $this->selectRows(
            'SELECT ' . self::ORDER_COLUMNS
            . " FROM `{$this->getPrefix()}orders` o"
            . $this->orderStateJoinSql()
            . " WHERE o.`id_customer` = $idCustomer"
            . ' AND o.`id_customer` IN (' . $this->linkedCustomersSql() . ')'
            . ' ORDER BY o.`date_add` DESC, o.`id_order` DESC LIMIT ' . $limit
        );
    }

    /**
     * @param int[] $orderIds
     *
     * @return array<int|string, array<int, array<string, string>>> keyed by id_order
     */
    public function findLines(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return $this->groupBy($this->selectRows(
            'SELECT `id_order`, `product_id`, `product_attribute_id`, `product_name`, `product_quantity`, `unit_price_tax_incl`'
            . " FROM `{$this->getPrefix()}order_detail`"
            . ' WHERE `id_order` IN (' . $this->escapeIds($orderIds) . ')'
            . ' ORDER BY `id_order_detail`'
        ), 'id_order');
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function findChangedCreditSlips(string $after, int $afterId, string $before, int $limit): array
    {
        return $this->selectRows(
            'SELECT s.`id_order_slip`, s.`id_order`, s.`total_products_tax_incl`, s.`total_shipping_tax_incl`,'
            . ' s.`date_add`, s.`date_upd`'
            . ' FROM (' . $this->linkedCustomersSql() . ') linked'
            . " JOIN `{$this->getPrefix()}order_slip` s ON s.`id_customer` = linked.`id_customer`"
            . ' WHERE ' . $this->afterCursorSql('s.`date_upd`', 's.`id_order_slip`', $after, $afterId, $before)
            . ' ORDER BY s.`date_upd`, s.`id_order_slip` LIMIT ' . $limit
        );
    }

    private function linkedCustomersSql(): string
    {
        return 'SELECT DISTINCT c.`id_customer`'
            . " FROM `{$this->getPrefix()}" . CartVisitorRepository::TABLE . '` v'
            . " JOIN `{$this->getPrefix()}cart` c ON c.`id_cart` = v.`id_cart`"
            . ' WHERE c.`id_customer` > 0';
    }

    private function orderStateJoinSql(): string
    {
        return " LEFT JOIN `{$this->getPrefix()}order_state` os ON os.`id_order_state` = o.`current_state`";
    }
}
