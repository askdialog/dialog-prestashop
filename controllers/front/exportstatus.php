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

use Dialog\AskDialog\Repository\ExportLogRepository;
use Dialog\AskDialog\Trait\JsonResponseTrait;

/**
 * Class AskDialogExportstatusModuleFrontController
 *
 * Protected API endpoint for export status monitoring
 * Allows S3 admin server to check export upload status
 */
class AskDialogExportstatusModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    /**
     * Initialize controller and verify private API key authentication
     */
    public function initContent()
    {
        parent::initContent();

        // Check if token is valid (use private API key for security)
        $headers = getallheaders();
        $authHeader = $this->getHeaderCaseInsensitive($headers, 'Authorization');

        if ($authHeader === null || substr($authHeader, 0, 6) !== 'Token ') {
            $this->sendJsonResponse(['error' => 'Private API Token is missing'], 401);
        }

        if ($authHeader !== 'Token ' . Configuration::get('ASKDIALOG_API_KEY')) {
            $this->sendJsonResponse(['error' => 'Private API Token is wrong'], 403);
        }

        $this->ajax = true;
    }

    /**
     * Main AJAX handler for export status actions
     */
    public function displayAjax()
    {
        $action = Tools::getValue('action');
        $exportLogRepo = new ExportLogRepository();

        switch ($action) {
            case 'getLatestStatus':
                $this->handleGetLatestStatus($exportLogRepo);
                break;

            case 'getExportHistory':
                $this->handleGetExportHistory($exportLogRepo);
                break;

            case 'getExportById':
                $this->handleGetExportById($exportLogRepo);
                break;

            case 'getStatusSummary':
                $this->handleGetStatusSummary($exportLogRepo);
                break;

            case 'cleanupOldLogs':
                $this->handleCleanupOldLogs($exportLogRepo);
                break;

            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action',
                ], 400);
        }
    }

    /**
     * Get the most recent export status for a shop and type
     *
     * Query params:
     * - export_type: Optional (catalog, cms) - defaults to catalog
     *
     * @param ExportLogRepository $exportLogRepo
     */
    private function handleGetLatestStatus($exportLogRepo)
    {
        $idShop = (int) $this->context->shop->id;
        $exportType = Tools::getValue('export_type', ExportLogRepository::EXPORT_TYPE_CATALOG);

        // Validate export type against allowed values
        if (!ExportLogRepository::isValidExportType($exportType)) {
            $this->sendJsonResponse([
                'status' => 'error',
                'message' => 'Invalid export_type. Allowed values: catalog, cms',
            ], 400);
        }

        $latestExport = $exportLogRepo->findLatestByType($idShop, $exportType);

        if (!$latestExport) {
            $this->sendJsonResponse([
                'status' => 'not_found',
                'message' => 'No export found for this shop and type',
            ], 404);
        }

        $this->sendJsonResponse($this->formatExportLog($latestExport));
    }

    /**
     * Get export history for a shop
     *
     * Query params:
     * - limit: Number of results (default: 10, max: 100)
     * - export_type: Optional filter by type (catalog, cms)
     *
     * @param ExportLogRepository $exportLogRepo
     */
    private function handleGetExportHistory($exportLogRepo)
    {
        $idShop = (int) $this->context->shop->id;
        $limit = min((int) Tools::getValue('limit', 10), 100);
        $exportType = Tools::getValue('export_type');

        // Convert empty string to null for proper filtering
        if (empty($exportType)) {
            $exportType = null;
        } elseif (!ExportLogRepository::isValidExportType($exportType)) {
            // Validate export type if provided
            $this->sendJsonResponse([
                'status' => 'error',
                'message' => 'Invalid export_type. Allowed values: catalog, cms',
            ], 400);
        }

        $history = $exportLogRepo->findLatestByShop($idShop, $limit, $exportType);

        $this->sendJsonResponse([
            'status' => 'success',
            'count' => count($history),
            'exports' => array_map([$this, 'formatExportLog'], $history),
        ]);
    }

    /**
     * Get a specific export by ID
     *
     * Query params:
     * - id: Export log ID (required)
     *
     * @param ExportLogRepository $exportLogRepo
     */
    private function handleGetExportById($exportLogRepo)
    {
        $idShop = (int) $this->context->shop->id;
        $exportId = (int) Tools::getValue('id');

        if (!$exportId) {
            $this->sendJsonResponse([
                'status' => 'error',
                'message' => 'Missing required parameter: id',
            ], 400);
        }

        $exportLog = $exportLogRepo->findById($exportId);

        if (!$exportLog) {
            $this->sendJsonResponse([
                'status' => 'not_found',
                'message' => 'Export not found',
            ], 404);
        }

        // Verify shop ownership
        if ((int) $exportLog['id_shop'] !== $idShop) {
            $this->sendJsonResponse([
                'status' => 'forbidden',
                'message' => 'Access denied to this export',
            ], 403);
        }

        $this->sendJsonResponse($this->formatExportLog($exportLog));
    }

    /**
     * Get status summary (count by status)
     *
     * @param ExportLogRepository $exportLogRepo
     */
    private function handleGetStatusSummary($exportLogRepo)
    {
        $idShop = (int) $this->context->shop->id;
        $statusCounts = $exportLogRepo->countByStatus($idShop);

        // Format response with default 0 for missing statuses
        $summary = [
            'status' => 'success',
            'id_shop' => $idShop,
            'counts' => [
                'init' => isset($statusCounts['init']) ? (int) $statusCounts['init']['count'] : 0,
                'pending' => isset($statusCounts['pending']) ? (int) $statusCounts['pending']['count'] : 0,
                'success' => isset($statusCounts['success']) ? (int) $statusCounts['success']['count'] : 0,
                'error' => isset($statusCounts['error']) ? (int) $statusCounts['error']['count'] : 0,
            ],
        ];

        $this->sendJsonResponse($summary);
    }

    /**
     * Cleanup old export logs (cron-style endpoint)
     *
     * Query params:
     * - days: Number of days to keep (default: 90, min: 7, max: 365)
     *
     * @param ExportLogRepository $exportLogRepo
     */
    private function handleCleanupOldLogs($exportLogRepo)
    {
        $days = (int) Tools::getValue('days', 90);

        // Validate days parameter (between 7 and 365)
        if ($days < 7) {
            $days = 7;
        } elseif ($days > 365) {
            $days = 365;
        }

        $deletedCount = $exportLogRepo->deleteOlderThan($days);

        if ($deletedCount !== false) {
            $this->sendJsonResponse([
                'status' => 'success',
                'message' => 'Export logs older than ' . $days . ' days have been deleted',
                'days_kept' => $days,
                'deleted_count' => $deletedCount,
            ]);
        } else {
            $this->sendJsonResponse([
                'status' => 'error',
                'message' => 'Failed to delete old export logs',
            ], 500);
        }
    }

    /**
     * Format export log for API response
     * Parses JSON metadata and sanitizes error messages
     *
     * @param array $exportLog Raw export log from database
     *
     * @return array Formatted export log
     */
    private function formatExportLog($exportLog)
    {
        $formatted = [
            'id' => (int) $exportLog['id_export_log'],
            'id_shop' => (int) $exportLog['id_shop'],
            'export_type' => $exportLog['export_type'],
            'status' => $exportLog['status'],
            'file_name' => $exportLog['file_name'],
            's3_url' => $exportLog['s3_url'],
            'started_at' => $exportLog['started_at'],
            'completed_at' => $exportLog['completed_at'],
        ];

        // Add error message (sanitized - no stack traces)
        if ($exportLog['error_message']) {
            $formatted['error_message'] = $this->sanitizeErrorMessage($exportLog['error_message']);
        }

        // Parse metadata JSON
        if ($exportLog['metadata']) {
            $metadata = json_decode($exportLog['metadata'], true);
            $formatted['metadata'] = $metadata ?: [];
        } else {
            $formatted['metadata'] = [];
        }

        return $formatted;
    }

    /**
     * Sanitize error message to avoid exposing sensitive information
     *
     * @param string $errorMessage Raw error message
     *
     * @return string Sanitized error message
     */
    private function sanitizeErrorMessage($errorMessage)
    {
        // Remove potential file paths
        $sanitized = preg_replace('#/[a-zA-Z0-9/_\-\.]+\.php#', '[PATH]', $errorMessage);

        // Truncate if too long (keep first 500 chars)
        if (strlen($sanitized) > 500) {
            $sanitized = substr($sanitized, 0, 500) . '...';
        }

        return $sanitized;
    }
}
