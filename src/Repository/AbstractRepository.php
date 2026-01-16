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
 * Abstract base class for all repositories
 * Provides common database access methods
 */
abstract class AbstractRepository
{
    /**
     * Get database instance
     *
     * @return \Db
     */
    protected function getDb()
    {
        return \Db::getInstance();
    }

    /**
     * Get database prefix
     *
     * @return string
     */
    protected function getPrefix()
    {
        return _DB_PREFIX_;
    }

    /**
     * Execute SQL query and return all results
     *
     * @param string $sql SQL query
     *
     * @return array|false
     */
    protected function executeS($sql)
    {
        return $this->getDb()->executeS($sql);
    }

    /**
     * Safely escape array of integers for SQL IN clause
     *
     * @param array $ids Array of IDs
     *
     * @return string Comma-separated escaped IDs
     */
    protected function escapeIds(array $ids)
    {
        return implode(',', array_map('intval', $ids));
    }

    /**
     * Index array results by a specific key for O(1) lookup
     *
     * @param array $results Query results
     * @param string $key Key to index by
     * @param bool $multiple Allow multiple values per key (returns array of arrays)
     *
     * @return array Indexed array
     */
    protected function indexBy(array $results, $key, $multiple = false)
    {
        $indexed = [];

        foreach ($results as $row) {
            if (!isset($row[$key])) {
                continue;
            }

            $keyValue = $row[$key];

            if ($multiple) {
                if (!isset($indexed[$keyValue])) {
                    $indexed[$keyValue] = [];
                }
                $indexed[$keyValue][] = $row;
            } else {
                $indexed[$keyValue] = $row;
            }
        }

        return $indexed;
    }

    /**
     * Group array results by a specific key
     * Alias for indexBy with multiple = true
     *
     * @param array $results Query results
     * @param string $key Key to group by
     *
     * @return array Grouped array
     */
    protected function groupBy(array $results, $key)
    {
        return $this->indexBy($results, $key, true);
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $tableName Table name (without prefix)
     * @param string $columnName Column name
     *
     * @return bool True if column exists
     */
    protected function columnExists($tableName, $columnName)
    {
        $sql = 'SHOW COLUMNS FROM `' . $this->getPrefix() . pSQL($tableName) . '` LIKE "' . pSQL($columnName) . '"';
        $result = $this->executeS($sql);

        return !empty($result);
    }
}
