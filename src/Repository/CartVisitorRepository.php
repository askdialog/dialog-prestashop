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
 * The Dialog visitor of each cart, with the consent level the widget reported
 *
 * Keyed by cart: an order keeps its cart's id, and the order hook can run from a payment
 * webhook with no browser, so the link is written while the shopper's browser saves the cart.
 */
class CartVisitorRepository extends AbstractRepository
{
    public const TABLE = 'askdialog_cart_visitor';

    public const CONSENT_LEVELS = ['a', 'am'];

    public static function createTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_cart` int(10) unsigned NOT NULL,
            `visitor_id` varchar(128) NOT NULL,
            `consent_level` varchar(2) NOT NULL,
            `date_add` datetime NOT NULL,
            `date_upd` datetime NOT NULL,
            PRIMARY KEY (`id_cart`),
            KEY `idx_date_upd` (`date_upd`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';
    }

    /**
     * Links a cart to a visitor; `date_upd` moves only when the visitor or the level changes.
     */
    public function save(int $idCart, string $visitorId, string $consentLevel): bool
    {
        $now = date('Y-m-d H:i:s');
        $visitor = pSQL($visitorId);
        $level = pSQL($consentLevel);

        // date_upd is assigned first: MySQL applies the assignments left to right
        return $this->getDb()->execute(
            'INSERT INTO `' . $this->getPrefix() . self::TABLE . '`'
            . ' (`id_cart`, `visitor_id`, `consent_level`, `date_add`, `date_upd`)'
            . " VALUES ($idCart, '$visitor', '$level', '$now', '$now')"
            . ' ON DUPLICATE KEY UPDATE'
            . " `date_upd` = IF(BINARY `visitor_id` = '$visitor' AND `consent_level` = '$level', `date_upd`, '$now'),"
            . " `visitor_id` = '$visitor', `consent_level` = '$level'"
        );
    }

    /**
     * The linked carts of a customer changed after the cursor and before `$before`, in
     * cursor order. A cart changes when PrestaShop saves it or when its link changes.
     *
     * @param string $after local datetime of the cursor
     * @param int $afterId cart id of the cursor, among the carts changed in that second
     * @param string $before local datetime, excluded
     * @param int[] $shopIds lets the cart side use PrestaShop's (id_shop, date_upd) index
     *
     * @return array<int, array<string, string>>
     */
    public function findChanged(string $after, int $afterId, string $before, array $shopIds, int $limit): array
    {
        $prefix = $this->getPrefix();
        $since = pSQL($after);
        $changedAt = 'GREATEST(c.`date_upd`, v.`date_upd`)';

        return $this->selectRows(
            'SELECT c.`id_cart`, c.`id_shop`, c.`id_customer`, c.`id_currency`,'
            . ' c.`id_address_delivery`, c.`id_address_invoice`, c.`date_add`,'
            . " v.`visitor_id`, v.`consent_level`, $changedAt AS `changed_at`"
            . ' FROM ('
            . "SELECT `id_cart` FROM `{$prefix}" . self::TABLE . "` WHERE `date_upd` >= '$since'"
            . " UNION SELECT `id_cart` FROM `{$prefix}cart`"
            . ' WHERE `id_shop` IN (' . $this->escapeIds($shopIds) . ") AND `date_upd` >= '$since'"
            . ') changed'
            . " JOIN `{$prefix}" . self::TABLE . '` v ON v.`id_cart` = changed.`id_cart`'
            . " JOIN `{$prefix}cart` c ON c.`id_cart` = changed.`id_cart`"
            . ' WHERE c.`id_customer` > 0'
            . ' AND ' . $this->afterCursorSql($changedAt, 'c.`id_cart`', $after, $afterId, $before)
            . ' ORDER BY `changed_at`, c.`id_cart`'
            . ' LIMIT ' . $limit
        );
    }
}
