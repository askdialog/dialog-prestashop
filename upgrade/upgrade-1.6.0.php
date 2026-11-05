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
 * Upgrade script for version 1.6.0
 * Creates the cart-visitor table and registers actionCartSave, which fills it.
 *
 * A failure is logged rather than failing the upgrade, which would disable the module:
 * without the table, carts are just not linked to their Dialog visitor.
 *
 * @param AskDialog $module
 *
 * @return bool
 */
function upgrade_module_1_6_0($module)
{
    if (!Db::getInstance()->execute(\Dialog\AskDialog\Repository\CartVisitorRepository::createTableSql())) {
        PrestaShopLogger::addLog('AskDialog 1.6.0: could not create the cart-visitor table', 3, null, 'Module', (int) $module->id);
    }

    if (!$module->registerHooks()) {
        PrestaShopLogger::addLog('AskDialog 1.6.0: could not bring the hooks up to date', 2, null, 'Module', (int) $module->id);
    }

    return true;
}
