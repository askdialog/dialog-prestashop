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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade script for version 1.7.0
 * Creates the cart-origin table the handoff controller claims its links in.
 *
 * The DEC-3660 spike, never released, created a table of the same name keyed on the cart
 * alone, which would let a cart receive only one link: it is dropped and recreated. No
 * released version wrote to it, so nothing a merchant relies on is lost.
 *
 * A failure is logged rather than failing the upgrade, which would disable the module:
 * without the table, a handoff link adds nothing and lands the shopper on the cart page.
 *
 * @param AskDialog $module
 *
 * @return bool
 */
function upgrade_module_1_7_0($module)
{
    $db = Db::getInstance();
    // Db throws on 8.x and returns false on 1.7: both are logged, neither fails the upgrade
    try {
        $created = $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . Dialog\AskDialog\Repository\CartOriginRepository::TABLE . '`')
            && $db->execute(Dialog\AskDialog\Repository\CartOriginRepository::createTableSql());
    } catch (Throwable $e) {
        $created = false;
    }
    if (!$created) {
        PrestaShopLogger::addLog('AskDialog 1.7.0: could not create the cart-origin table', 3, null, 'Module', (int) $module->id);
    }

    return true;
}
