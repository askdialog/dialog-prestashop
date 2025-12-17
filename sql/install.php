<?php
/**
 * 2007-2025 Dialog
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
 * @copyright 2007-2025 Dialog
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

// Create table to store products pending export to Dialog S3 server
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'askdialog_product` (
    `id_askdialog_product` int(11) NOT NULL AUTO_INCREMENT,
    `id_product` int(11) NOT NULL,
    `id_shop` int(11) NOT NULL,
    `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_askdialog_product`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
