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

namespace Dialog\AskDialog\Helper;

/**
 * Class PathHelper
 * 
 * Provides centralized path management for module directories
 * Follows PrestaShop best practices by using /var/modules/ for temporary and dynamic files
 * 
 * @package Dialog\AskDialog\Helper
 */
class PathHelper
{
    /**
     * Gets the temporary directory path for module files
     * Creates directory if it doesn't exist
     *
     * @return string Absolute path to tmp directory (with trailing slash)
     */
    public static function getTmpDir(): string
    {
        $dir = _PS_ROOT_DIR_ . '/var/modules/askdialog/tmp/';
        
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
        }
        
        return $dir;
    }

    /**
     * Gets the sent files archive directory path
     * Creates directory if it doesn't exist
     *
     * @return string Absolute path to sent directory (with trailing slash)
     */
    public static function getSentDir(): string
    {
        $dir = _PS_ROOT_DIR_ . '/var/modules/askdialog/sent/';
        
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
        }
        
        return $dir;
    }

    /**
     * Gets the cache directory path for module
     * Creates directory if it doesn't exist
     *
     * @return string Absolute path to cache directory (with trailing slash)
     */
    public static function getCacheDir(): string
    {
        $dir = _PS_ROOT_DIR_ . '/var/modules/askdialog/cache/';
        
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
        }
        
        return $dir;
    }

    /**
     * Cleans up temporary files older than specified age
     *
     * @param int $maxAge Maximum age in seconds (default: 24h)
     * @return int Number of files deleted
     */
    public static function cleanTmpFiles(int $maxAge = 86400): int
    {
        $count = 0;
        $tmpDir = self::getTmpDir();
        $files = glob($tmpDir . '*');
        
        if ($files === false) {
            return 0;
        }
        
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > $maxAge)) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }

    /**
     * Ensures all module directories exist with correct permissions
     *
     * @return bool True if all directories were created/verified successfully
     */
    public static function ensureDirectoriesExist(): bool
    {
        try {
            self::getTmpDir();
            self::getSentDir();
            self::getCacheDir();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generates a unique filename with timestamp and hash
     *
     * @param string $prefix Filename prefix (e.g., 'catalog', 'cms')
     * @param string $extension File extension (default: 'json')
     * @return string Generated filename (e.g., 'catalog_20250119_143025_a1b2c3d4.json')
     */
    public static function generateUniqueFilename(string $prefix, string $extension = 'json'): string
    {
        $timestamp = date('Ymd_His');
        $hash = substr(md5($timestamp . rand()), 0, 8);
        return $prefix . '_' . $timestamp . '_' . $hash . '.' . $extension;
    }

    /**
     * Generates a unique file path in the temporary directory
     *
     * @param string $prefix Filename prefix (e.g., 'catalog', 'cms')
     * @param string $extension File extension (default: 'json')
     * @return string Full path to the file in tmp directory
     */
    public static function generateTmpFilePath(string $prefix, string $extension = 'json'): string
    {
        $filename = self::generateUniqueFilename($prefix, $extension);
        return self::getTmpDir() . $filename;
    }
}
