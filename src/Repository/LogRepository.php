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
 * Repository for PrestaShop log table (ps_log)
 * Provides read access to system logs for monitoring
 */
class LogRepository extends AbstractRepository
{
    /**
     * Log severity levels (PrestaShop standard)
     */
    public const SEVERITY_INFO = 1;
    public const SEVERITY_WARNING = 2;
    public const SEVERITY_ERROR = 3;
    public const SEVERITY_CRITICAL = 4;

    /**
     * Maximum allowed limit for log retrieval
     */
    public const MAX_LIMIT = 1000;

    /**
     * Default limit for log retrieval
     */
    public const DEFAULT_LIMIT = 100;

    /**
     * Find latest logs ordered by most recent first
     *
     * @param int $limit Number of logs to retrieve (max: 1000)
     * @param int|null $severity Optional severity filter (1=info, 2=warning, 3=error, 4=critical)
     * @param string|null $search Optional search string in message
     *
     * @return array Array of log entries
     */
    public function findLatest($limit = self::DEFAULT_LIMIT, $severity = null, $search = null)
    {
        // Enforce limit boundaries
        $limit = max(1, min((int) $limit, self::MAX_LIMIT));

        $sql = 'SELECT `id_log`, `severity`, `error_code`, `message`, `date_add`
                FROM `' . $this->getPrefix() . 'log`
                WHERE 1=1';

        if ($severity !== null) {
            $sql .= ' AND `severity` = ' . (int) $severity;
        }

        if ($search !== null && !empty($search)) {
            $sql .= ' AND `message` LIKE "%' . pSQL($search) . '%"';
        }

        $sql .= ' ORDER BY `id_log` DESC
                  LIMIT ' . $limit;

        $results = $this->executeS($sql);

        return $results ?: [];
    }

    /**
     * Find logs by severity level
     *
     * @param int $severity Severity level (1-4)
     * @param int $limit Number of logs to retrieve
     *
     * @return array Array of log entries
     */
    public function findBySeverity($severity, $limit = self::DEFAULT_LIMIT)
    {
        return $this->findLatest($limit, $severity);
    }

    /**
     * Find logs containing a specific search string
     *
     * @param string $search Search string
     * @param int $limit Number of logs to retrieve
     * @param int|null $severity Optional severity filter
     *
     * @return array Array of log entries
     */
    public function findBySearch($search, $limit = self::DEFAULT_LIMIT, $severity = null)
    {
        return $this->findLatest($limit, $severity, $search);
    }

    /**
     * Count logs by severity
     *
     * @return array Associative array with severity counts
     */
    public function countBySeverity()
    {
        $sql = 'SELECT `severity`, COUNT(*) as `count`
                FROM `' . $this->getPrefix() . 'log`
                GROUP BY `severity`';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'severity');
    }

    /**
     * Get all valid severity levels
     *
     * @return array List of valid severity levels
     */
    public static function getValidSeverityLevels()
    {
        return [
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_ERROR,
            self::SEVERITY_CRITICAL,
        ];
    }

    /**
     * Check if severity level is valid
     *
     * @param int $severity Severity level to validate
     *
     * @return bool True if valid, false otherwise
     */
    public static function isValidSeverity($severity)
    {
        return in_array((int) $severity, self::getValidSeverityLevels(), true);
    }

    /**
     * Get severity label
     *
     * @param int $severity Severity level
     *
     * @return string Severity label
     */
    public static function getSeverityLabel($severity)
    {
        $labels = [
            self::SEVERITY_INFO => 'info',
            self::SEVERITY_WARNING => 'warning',
            self::SEVERITY_ERROR => 'error',
            self::SEVERITY_CRITICAL => 'critical',
        ];

        return isset($labels[$severity]) ? $labels[$severity] : 'unknown';
    }
}
