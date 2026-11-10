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
 * The handoff links each cart received, one row per cart and link
 *
 * A claimed pair makes a link idempotent in its cart: a reload or a double click adds
 * nothing, while several links into one cart each add their lines. An order keeps its
 * cart's id, so the rows also tell which orders came from a handed-over cart.
 */
class CartOriginRepository extends AbstractRepository
{
    public const TABLE = 'askdialog_cart_origin';

    /**
     * `handoff_ref` is base64url, so it compares case-sensitively (ascii_bin)
     */
    public static function createTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_cart` int(10) unsigned NOT NULL,
            `handoff_ref` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `origin` varchar(32) NOT NULL,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_cart`, `handoff_ref`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';
    }

    /**
     * Records that a cart received a link, unless it already did
     *
     * @return bool false when the cart already received this link
     *
     * @throws \RuntimeException when the row could not be written
     */
    public function claim(int $idCart, string $handoffRef, string $origin): bool
    {
        $db = $this->getDb();
        $written = $db->execute(
            'INSERT IGNORE INTO `' . $this->getPrefix() . self::TABLE . '`'
            . ' (`id_cart`, `handoff_ref`, `origin`, `date_add`)'
            . " VALUES ($idCart, '" . pSQL($handoffRef) . "', '" . pSQL($origin) . "', '" . date('Y-m-d H:i:s') . "')"
        );
        if (!$written) {
            throw new \RuntimeException('Query failed: ' . $db->getMsgError());
        }

        return (int) $db->Affected_Rows() === 1;
    }

    /**
     * Forgets a claim whose link added nothing, so that the link can be followed again
     */
    public function release(int $idCart, string $handoffRef): bool
    {
        return $this->getDb()->delete(
            self::TABLE,
            '`id_cart` = ' . $idCart . " AND `handoff_ref` = '" . pSQL($handoffRef) . "'"
        );
    }
}
