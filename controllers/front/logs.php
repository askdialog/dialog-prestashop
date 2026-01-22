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

use Dialog\AskDialog\Repository\LogRepository;
use Dialog\AskDialog\Traits\JsonResponseTrait;

/**
 * Class AskDialogLogsModuleFrontController
 *
 * Protected API endpoint for PrestaShop log monitoring
 * Allows Dialog admin server to retrieve system logs
 */
class AskDialogLogsModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    /**
     * Initialize controller and verify private API key authentication
     */
    public function initContent()
    {
        parent::initContent();

        $token = $this->getApiToken();

        if ($token === null) {
            $this->sendJsonResponse(['error' => 'Private API Token is missing'], 401);
        }

        if ($token !== \Configuration::get('ASKDIALOG_API_KEY')) {
            $this->sendJsonResponse(['error' => 'Private API Token is wrong'], 403);
        }

        $this->ajax = true;
    }

    /**
     * Get API token from X-Api-Key header
     *
     * @return string|null Token value
     */
    private function getApiToken()
    {
        // 1. X-Api-Key header via getallheaders()
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $xApiKey = $this->getHeaderCaseInsensitive($headers, 'X-Api-Key');
            if ($xApiKey !== null) {
                return $xApiKey;
            }
        }

        // 2. $_SERVER fallback (FastCGI, CGI, some shared hosting)
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        return null;
    }

    /**
     * Get header value case-insensitively
     *
     * @param array $headers Headers array
     * @param string $name Header name to find
     *
     * @return string|null Header value or null if not found
     */
    private function getHeaderCaseInsensitive($headers, $name)
    {
        $nameLower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $nameLower) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Main AJAX handler for log retrieval
     */
    public function displayAjax()
    {
        $action = Tools::getValue('action', 'getLatest');
        $logRepo = new LogRepository();

        switch ($action) {
            case 'getLatest':
                $this->handleGetLatest($logRepo);
                break;

            case 'getSummary':
                $this->handleGetSummary($logRepo);
                break;

            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action. Allowed: getLatest, getSummary',
                ], 400);
        }
    }

    /**
     * Get latest logs with optional filtering
     *
     * Query params:
     * - limit: Number of logs to retrieve (default: 100, max: 1000)
     * - severity: Optional severity filter (1=info, 2=warning, 3=error, 4=critical)
     * - search: Optional search string in message
     *
     * @param LogRepository $logRepo
     */
    private function handleGetLatest($logRepo)
    {
        $limit = (int) Tools::getValue('limit', LogRepository::DEFAULT_LIMIT);
        $severity = Tools::getValue('severity');
        $search = Tools::getValue('search');

        // Validate severity if provided
        if ($severity !== false && $severity !== '') {
            $severity = (int) $severity;
            if (!LogRepository::isValidSeverity($severity)) {
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid severity. Allowed values: 1 (info), 2 (warning), 3 (error), 4 (critical)',
                ], 400);
            }
        } else {
            $severity = null;
        }

        // Convert empty search to null
        if (empty($search)) {
            $search = null;
        }

        $logs = $logRepo->findLatest($limit, $severity, $search);

        $this->sendJsonResponse([
            'status' => 'success',
            'count' => count($logs),
            'logs' => array_map([$this, 'formatLog'], $logs),
        ]);
    }

    /**
     * Get summary of logs by severity
     *
     * @param LogRepository $logRepo
     */
    private function handleGetSummary($logRepo)
    {
        $severityCounts = $logRepo->countBySeverity();

        $summary = [
            'status' => 'success',
            'counts' => [
                'info' => isset($severityCounts[LogRepository::SEVERITY_INFO])
                    ? (int) $severityCounts[LogRepository::SEVERITY_INFO]['count'] : 0,
                'warning' => isset($severityCounts[LogRepository::SEVERITY_WARNING])
                    ? (int) $severityCounts[LogRepository::SEVERITY_WARNING]['count'] : 0,
                'error' => isset($severityCounts[LogRepository::SEVERITY_ERROR])
                    ? (int) $severityCounts[LogRepository::SEVERITY_ERROR]['count'] : 0,
                'critical' => isset($severityCounts[LogRepository::SEVERITY_CRITICAL])
                    ? (int) $severityCounts[LogRepository::SEVERITY_CRITICAL]['count'] : 0,
            ],
        ];

        $this->sendJsonResponse($summary);
    }

    /**
     * Format log entry for API response
     *
     * @param array $log Raw log from database
     *
     * @return array Formatted log
     */
    private function formatLog($log)
    {
        return [
            'id_log' => (int) $log['id_log'],
            'severity' => (int) $log['severity'],
            'severity_label' => LogRepository::getSeverityLabel((int) $log['severity']),
            'error_code' => (int) $log['error_code'],
            'message' => $log['message'],
            'date_add' => $log['date_add'],
        ];
    }
}
