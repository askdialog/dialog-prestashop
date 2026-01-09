<?php
/*
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
*  @author Axel Paillaud <contact@axelweb.fr>
*  @copyright  2007-2025 Dialog
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

namespace Dialog\AskDialog\Repository;

/**
 * Repository for export log tracking
 * Handles CRUD operations for S3 export status monitoring
 *
 * @package Dialog\AskDialog\Repository
 */
class ExportLogRepository extends AbstractRepository
{
    const STATUS_INIT = 'init';
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';

    const EXPORT_TYPE_CATALOG = 'catalog';
    const EXPORT_TYPE_CMS = 'cms';

    /**
     * Create a new export log entry
     *
     * @param int $idShop Shop ID
     * @param string $exportType Type of export (catalog, cms)
     * @param array $metadata Optional metadata as associative array
     * @return int|false Insert ID or false on failure
     */
    public function createLog($idShop, $exportType, array $metadata = [])
    {
        $metadataJson = !empty($metadata) ? json_encode($metadata) : null;

        $sql = 'INSERT INTO `' . $this->getPrefix() . 'askdialog_export_log`
                (`id_shop`, `export_type`, `status`, `metadata`, `started_at`)
                VALUES (
                    ' . (int)$idShop . ',
                    "' . pSQL($exportType) . '",
                    "' . self::STATUS_INIT . '",
                    ' . ($metadataJson ? '"' . pSQL($metadataJson) . '"' : 'NULL') . ',
                    NOW()
                )';

        $result = $this->getDb()->execute($sql);

        return $result ? (int)$this->getDb()->Insert_ID() : false;
    }

    /**
     * Update export log status
     *
     * @param int $idExportLog Export log ID
     * @param string $status New status (init, pending, success, error)
     * @param array $data Optional data to update (file_name, s3_url, error_message)
     * @return bool True on success
     */
    public function updateStatus($idExportLog, $status, array $data = [])
    {
        $updates = ['`status` = "' . pSQL($status) . '"'];

        // Update completed_at for terminal states
        if (in_array($status, [self::STATUS_SUCCESS, self::STATUS_ERROR])) {
            $updates[] = '`completed_at` = NOW()';
        }

        // Handle optional fields
        if (isset($data['file_name'])) {
            $updates[] = '`file_name` = "' . pSQL($data['file_name']) . '"';
        }

        if (isset($data['s3_url'])) {
            $updates[] = '`s3_url` = "' . pSQL($data['s3_url']) . '"';
        }

        if (isset($data['error_message'])) {
            $updates[] = '`error_message` = "' . pSQL($data['error_message']) . '"';
        }

        if (isset($data['metadata'])) {
            $metadataJson = json_encode($data['metadata']);
            $updates[] = '`metadata` = "' . pSQL($metadataJson) . '"';
        }

        $sql = 'UPDATE `' . $this->getPrefix() . 'askdialog_export_log`
                SET ' . implode(', ', $updates) . '
                WHERE `id_export_log` = ' . (int)$idExportLog;

        return $this->getDb()->execute($sql);
    }

    /**
     * Find export log by ID
     *
     * @param int $idExportLog Export log ID
     * @return array|false Export log data or false if not found
     */
    public function findById($idExportLog)
    {
        $sql = 'SELECT *
                FROM `' . $this->getPrefix() . 'askdialog_export_log`
                WHERE `id_export_log` = ' . (int)$idExportLog;

        $result = $this->executeS($sql);

        return !empty($result) ? $result[0] : false;
    }

    /**
     * Find latest export logs for a shop
     *
     * @param int $idShop Shop ID
     * @param int $limit Number of results to return
     * @param string|null $exportType Optional filter by export type
     * @return array Array of export logs
     */
    public function findLatestByShop($idShop, $limit = 10, $exportType = null)
    {
        $sql = 'SELECT *
                FROM `' . $this->getPrefix() . 'askdialog_export_log`
                WHERE `id_shop` = ' . (int)$idShop;

        if ($exportType !== null) {
            $sql .= ' AND `export_type` = "' . pSQL($exportType) . '"';
        }

        $sql .= ' ORDER BY `started_at` DESC
                  LIMIT ' . (int)$limit;

        $results = $this->executeS($sql);

        return $results ?: [];
    }

    /**
     * Find export logs by status
     *
     * @param int $idShop Shop ID
     * @param string $status Status to filter by
     * @param int $limit Number of results to return
     * @return array Array of export logs
     */
    public function findByStatus($idShop, $status, $limit = 10)
    {
        $sql = 'SELECT *
                FROM `' . $this->getPrefix() . 'askdialog_export_log`
                WHERE `id_shop` = ' . (int)$idShop . '
                  AND `status` = "' . pSQL($status) . '"
                ORDER BY `started_at` DESC
                LIMIT ' . (int)$limit;

        $results = $this->executeS($sql);

        return $results ?: [];
    }

    /**
     * Get the most recent export log for a shop and type
     *
     * @param int $idShop Shop ID
     * @param string $exportType Export type
     * @return array|false Most recent export log or false if none found
     */
    public function findLatestByType($idShop, $exportType)
    {
        $sql = 'SELECT *
                FROM `' . $this->getPrefix() . 'askdialog_export_log`
                WHERE `id_shop` = ' . (int)$idShop . '
                  AND `export_type` = "' . pSQL($exportType) . '"
                ORDER BY `started_at` DESC
                LIMIT 1';

        $result = $this->executeS($sql);

        return !empty($result) ? $result[0] : false;
    }

    /**
     * Delete export logs older than specified days
     *
     * @param int $days Number of days to keep
     * @return int|false Number of deleted rows or false on failure
     */
    public function deleteOlderThan($days)
    {
        $sql = 'DELETE FROM `' . $this->getPrefix() . 'askdialog_export_log`
                WHERE `started_at` < DATE_SUB(NOW(), INTERVAL ' . (int)$days . ' DAY)';

        $result = $this->getDb()->execute($sql);

        return $result ? (int)$this->getDb()->Affected_Rows() : false;
    }

    /**
     * Count export logs by status for a shop
     *
     * @param int $idShop Shop ID
     * @return array Associative array with status counts
     */
    public function countByStatus($idShop)
    {
        $sql = 'SELECT `status`, COUNT(*) as `count`
                FROM `' . $this->getPrefix() . 'askdialog_export_log`
                WHERE `id_shop` = ' . (int)$idShop . '
                GROUP BY `status`';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'status');
    }
}
