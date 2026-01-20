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
 * Upgrade script for version 1.0.1
 * Adds ASKDIALOG_BATCH_SIZE configuration key for existing installations
 *
 * @param AskDialog $module
 *
 * @return bool
 */
function upgrade_module_1_0_1($module)
{
    // Add batch size configuration with default value 5000
    // Only set if not already configured
    if (Configuration::get('ASKDIALOG_BATCH_SIZE') === false) {
        Configuration::updateValue('ASKDIALOG_BATCH_SIZE', 5000);
    }

    return true;
}
