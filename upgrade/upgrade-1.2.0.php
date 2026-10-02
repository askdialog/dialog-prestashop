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
 * Upgrade script for version 1.2.0
 * Writes the AI button mode switch, off: existing shops keep the product page input until they turn it on.
 *
 * An unwritten key reads as off too, so a failed write is logged rather than failing the upgrade,
 * which would disable the module.
 *
 * @param AskDialog $module
 *
 * @return bool
 */
function upgrade_module_1_2_0($module)
{
    if (!Configuration::updateValue('ASKDIALOG_AI_BUTTON_MODE', false)) {
        PrestaShopLogger::addLog('AskDialog 1.2.0: could not write ASKDIALOG_AI_BUTTON_MODE, it reads as off', 2, null, 'Module', (int) $module->id);
    }

    return true;
}
