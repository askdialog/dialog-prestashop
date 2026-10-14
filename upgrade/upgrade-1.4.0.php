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
 * Upgrade script for version 1.4.0
 * Registers displayHeader, which loads the storefront tag on every front-office page.
 *
 * A failed registration only leaves storefront events off, so it is logged rather than failing the upgrade,
 * which would disable the module.
 *
 * @param AskDialog $module
 *
 * @return bool
 */
function upgrade_module_1_4_0($module)
{
    if (!$module->registerHook('displayHeader')) {
        PrestaShopLogger::addLog('AskDialog 1.4.0: could not register displayHeader, the storefront tag is not loaded', 2, null, 'Module', (int) $module->id);
    }

    return true;
}
