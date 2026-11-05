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
}
