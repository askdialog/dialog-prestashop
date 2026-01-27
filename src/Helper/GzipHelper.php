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

namespace Dialog\AskDialog\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Helper for gzip file compression
 * Uses streaming to avoid loading entire files into memory
 */
class GzipHelper
{
    /** @var int Read buffer size in bytes (512 KB) */
    private const CHUNK_SIZE = 524288;

    /**
     * Compress a file to gzip format using streaming (memory-safe).
     * Reads the source file in chunks and writes compressed data incrementally.
     *
     * @param string $sourcePath Absolute path to the source file
     *
     * @return string Absolute path to the compressed .gz file
     *
     * @throws \Exception If compression fails
     */
    public static function compress($sourcePath)
    {
        if (!file_exists($sourcePath)) {
            Logger::error('[AskDialog] GzipHelper: Source file not found: ' . $sourcePath);
            throw new \Exception('GzipHelper: Source file not found: ' . $sourcePath);
        }

        $gzipPath = $sourcePath . '.gz';
        $originalSize = filesize($sourcePath);
        Logger::info('[AskDialog] GzipHelper: Compressing ' . basename($sourcePath) . ' (' . round($originalSize / 1024 / 1024, 2) . 'MB)...');

        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            Logger::error('[AskDialog] GzipHelper: Cannot open source file: ' . $sourcePath);
            throw new \Exception('GzipHelper: Cannot open source file: ' . $sourcePath);
        }

        $dest = gzopen($gzipPath, 'wb9');
        if ($dest === false) {
            fclose($source);
            Logger::error('[AskDialog] GzipHelper: Cannot create gzip file: ' . $gzipPath);
            throw new \Exception('GzipHelper: Cannot create gzip file: ' . $gzipPath);
        }

        try {
            while (!feof($source)) {
                $chunk = fread($source, self::CHUNK_SIZE);
                if ($chunk === false) {
                    Logger::error('[AskDialog] GzipHelper: Error reading source file');
                    throw new \Exception('GzipHelper: Error reading source file');
                }
                if (gzwrite($dest, $chunk) === false) {
                    Logger::error('[AskDialog] GzipHelper: Error writing gzip data');
                    throw new \Exception('GzipHelper: Error writing gzip data');
                }
            }
        } finally {
            fclose($source);
            gzclose($dest);
        }

        $compressedSize = filesize($gzipPath);
        $ratio = $originalSize > 0 ? round((1 - $compressedSize / $originalSize) * 100, 1) : 0;
        Logger::info(
            '[AskDialog] GzipHelper: Compressed ' . basename($sourcePath)
            . ' (' . round($originalSize / 1024 / 1024, 2) . 'MB'
            . ' → ' . round($compressedSize / 1024 / 1024, 2) . 'MB'
            . ', -' . $ratio . '%)'
        );

        return $gzipPath;
    }
}
