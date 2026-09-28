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
 * Upgrade script for version 1.1.11
 * Moves ASKDIALOG_API_URL from the core API Gateway to the Dialog monolith, which
 * serves the domain validation and catalog upload routes directly.
 *
 * Install stores the URL in the configuration table, so existing shops keep the
 * gateway URL unless it is rewritten here. Only the two gateway URLs Dialog ever
 * shipped are rewritten: any other value is a deliberate override.
 *
 * @param AskDialog $module
 *
 * @return bool
 */
function upgrade_module_1_1_11($module)
{
    $gatewayToMonolith = [
        'https://rtbzcxkmwj.execute-api.eu-west-1.amazonaws.com' => AskDialog::DIALOG_API_URL,
        'https://hr5buzenb1.execute-api.eu-west-1.amazonaws.com' => 'https://api-staging.askdialog.ai',
    ];

    $db = Db::getInstance();
    // Every scope at once: a multistore install can hold one row per shop or group.
    $rows = $db->executeS(
        'SELECT `id_configuration`, `value` FROM `' . _DB_PREFIX_ . 'configuration`'
        . " WHERE `name` = 'ASKDIALOG_API_URL'"
    );

    foreach ($rows ?: [] as $row) {
        $currentUrl = rtrim((string) $row['value'], '/');
        if (!isset($gatewayToMonolith[$currentUrl])) {
            continue;
        }

        $updated = $db->update(
            'configuration',
            [
                'value' => pSQL($gatewayToMonolith[$currentUrl]),
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            '`id_configuration` = ' . (int) $row['id_configuration']
        );
        if (!$updated) {
            return false;
        }
    }

    return true;
}
