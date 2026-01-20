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
 * Repository for export state management
 * Handles resumable exports with timeout protection
 */
class ExportStateRepository extends AbstractRepository
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const EXPORT_TYPE_CATALOG = 'catalog';
    public const EXPORT_TYPE_CMS = 'cms';

    /**
     * Find existing in-progress export state for a shop and type
     *
     * @param int $idShop Shop ID
     * @param string $exportType Export type (catalog, cms)
     *
     * @return array|false Export state data or false if none found
     */
    public function findInProgress($idShop, $exportType)
    {
        $sql = 'SELECT *
                FROM `' . $this->getPrefix() . 'askdialog_export_state`
                WHERE `id_shop` = ' . (int) $idShop . '
                  AND `export_type` = "' . pSQL($exportType) . '"
                  AND `status` = "' . self::STATUS_IN_PROGRESS . '"
                LIMIT 1';

        $result = $this->executeS($sql);

        return !empty($result) ? $result[0] : false;
    }

    /**
     * Find export state by ID
     *
     * @param int $idExportState Export state ID
     *
     * @return array|false Export state data or false if not found
     */
    public function findById($idExportState)
    {
        $sql = 'SELECT *
                FROM `' . $this->getPrefix() . 'askdialog_export_state`
                WHERE `id_export_state` = ' . (int) $idExportState;

        $result = $this->executeS($sql);

        return !empty($result) ? $result[0] : false;
    }

    /**
     * Create a new export state entry
     * Returns false if export already in progress (UNIQUE constraint on id_shop + export_type)
     *
     * @param int $idShop Shop ID
     * @param string $exportType Export type (catalog, cms)
     * @param int $totalProducts Total number of products to export
     * @param int $batchSize Batch size for processing
     * @param int $idLang Language ID
     * @param string $countryCode Country code
     * @param string|null $tmpFilePath Path to temporary file
     *
     * @return int|false Insert ID or false on failure (including duplicate)
     */
    public function create($idShop, $exportType, $totalProducts, $batchSize, $idLang, $countryCode, $tmpFilePath = null)
    {
        $sql = 'INSERT INTO `' . $this->getPrefix() . 'askdialog_export_state`
                (`id_shop`, `export_type`, `status`, `total_products`, `products_exported`,
                 `batch_size`, `tmp_file_path`, `id_lang`, `country_code`, `started_at`, `updated_at`)
                VALUES (
                    ' . (int) $idShop . ',
                    "' . pSQL($exportType) . '",
                    "' . self::STATUS_IN_PROGRESS . '",
                    ' . (int) $totalProducts . ',
                    0,
                    ' . (int) $batchSize . ',
                    ' . ($tmpFilePath ? '"' . pSQL($tmpFilePath) . '"' : 'NULL') . ',
                    ' . (int) $idLang . ',
                    "' . pSQL($countryCode) . '",
                    NOW(),
                    NOW()
                )';

        $result = $this->getDb()->execute($sql);

        return $result ? (int) $this->getDb()->Insert_ID() : false;
    }

    /**
     * Update export progress
     *
     * @param int $idExportState Export state ID
     * @param int $productsExported Number of products exported so far
     * @param string|null $tmpFilePath Optional: update temp file path
     *
     * @return bool True on success
     */
    public function updateProgress($idExportState, $productsExported, $tmpFilePath = null)
    {
        $updates = [
            '`products_exported` = ' . (int) $productsExported,
            '`updated_at` = NOW()',
        ];

        if ($tmpFilePath !== null) {
            $updates[] = '`tmp_file_path` = "' . pSQL($tmpFilePath) . '"';
        }

        $sql = 'UPDATE `' . $this->getPrefix() . 'askdialog_export_state`
                SET ' . implode(', ', $updates) . '
                WHERE `id_export_state` = ' . (int) $idExportState;

        return $this->getDb()->execute($sql);
    }

    /**
     * Mark export as completed
     *
     * @param int $idExportState Export state ID
     *
     * @return bool True on success
     */
    public function markCompleted($idExportState)
    {
        $sql = 'UPDATE `' . $this->getPrefix() . 'askdialog_export_state`
                SET `status` = "' . self::STATUS_COMPLETED . '",
                    `updated_at` = NOW()
                WHERE `id_export_state` = ' . (int) $idExportState;

        return $this->getDb()->execute($sql);
    }

    /**
     * Mark export as failed
     *
     * @param int $idExportState Export state ID
     *
     * @return bool True on success
     */
    public function markFailed($idExportState)
    {
        $sql = 'UPDATE `' . $this->getPrefix() . 'askdialog_export_state`
                SET `status` = "' . self::STATUS_FAILED . '",
                    `updated_at` = NOW()
                WHERE `id_export_state` = ' . (int) $idExportState;

        return $this->getDb()->execute($sql);
    }

    /**
     * Delete export state entry
     * Call after successful upload to S3
     *
     * @param int $idExportState Export state ID
     *
     * @return bool True on success
     */
    public function delete($idExportState)
    {
        $sql = 'DELETE FROM `' . $this->getPrefix() . 'askdialog_export_state`
                WHERE `id_export_state` = ' . (int) $idExportState;

        return $this->getDb()->execute($sql);
    }

    /**
     * Delete stale export states (older than specified hours)
     * Useful for cleaning up abandoned exports
     *
     * @param int $hours Number of hours
     *
     * @return int|false Number of deleted rows or false on failure
     */
    public function deleteStale($hours = 24)
    {
        $sql = 'DELETE FROM `' . $this->getPrefix() . 'askdialog_export_state`
                WHERE `updated_at` < DATE_SUB(NOW(), INTERVAL ' . (int) $hours . ' HOUR)';

        $result = $this->getDb()->execute($sql);

        return $result ? (int) $this->getDb()->Affected_Rows() : false;
    }

    /**
     * Calculate progress percentage
     *
     * @param array $state Export state data
     *
     * @return int Progress percentage (0-100)
     */
    public static function calculateProgress(array $state)
    {
        if (empty($state['total_products']) || $state['total_products'] <= 0) {
            return 0;
        }

        $progress = (int) (($state['products_exported'] / $state['total_products']) * 100);

        return min(100, max(0, $progress));
    }
}
