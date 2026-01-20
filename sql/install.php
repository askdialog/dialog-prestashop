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

$sql = [];

// Create table to track export status for Dialog S3 uploads
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'askdialog_export_log` (
    `id_export_log` int(11) NOT NULL AUTO_INCREMENT,
    `id_shop` int(11) NOT NULL,
    `export_type` varchar(50) NOT NULL,
    `status` enum(\'init\',\'pending\',\'success\',\'error\') NOT NULL DEFAULT \'init\',
    `file_name` varchar(255) DEFAULT NULL,
    `s3_url` text DEFAULT NULL,
    `error_message` text DEFAULT NULL,
    `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` datetime DEFAULT NULL,
    `metadata` text DEFAULT NULL,
    PRIMARY KEY (`id_export_log`),
    KEY `idx_shop_status` (`id_shop`, `status`),
    KEY `idx_started_at` (`started_at`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

// Create table to store Dialog appearance settings (JSON format for flexibility)
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'askdialog_appearance` (
    `id_appearance` int(11) NOT NULL AUTO_INCREMENT,
    `id_shop` int(11) NOT NULL,
    `settings` JSON NOT NULL,
    `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_appearance`),
    UNIQUE KEY `idx_id_shop` (`id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

// Create table to track export state for resumable exports (handles timeout/interruptions)
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'askdialog_export_state` (
    `id_export_state` int(11) NOT NULL AUTO_INCREMENT,
    `id_shop` int(11) NOT NULL,
    `export_type` varchar(50) NOT NULL,
    `status` enum(\'in_progress\',\'completed\',\'failed\') NOT NULL DEFAULT \'in_progress\',
    `total_products` int(11) NOT NULL,
    `products_exported` int(11) NOT NULL DEFAULT 0,
    `batch_size` int(11) NOT NULL,
    `tmp_file_path` varchar(255) DEFAULT NULL,
    `id_lang` int(11) NOT NULL,
    `country_code` varchar(10) NOT NULL,
    `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_export_state`),
    UNIQUE KEY `idx_shop_type` (`id_shop`, `export_type`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
