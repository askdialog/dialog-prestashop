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
 * @author    Axel Paillaud <contact@axelweb.fr>
 * @copyright 2026 Dialog
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Dialog\AskDialog\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Form\GeneralDataConfiguration;

/**
 * Logger wrapper that respects the ASKDIALOG_ENABLE_LOGS configuration
 */
class Logger
{
    /**
     * Log severity levels (same as PrestaShopLogger)
     */
    public const DEBUG = 1;
    public const INFO = 1;
    public const WARNING = 2;
    public const ERROR = 3;

    /**
     * Cached value of logging enabled setting
     *
     * @var bool|null
     */
    private static $enabled = null;

    /**
     * Add a log entry if logging is enabled
     *
     * @param string $message Log message
     * @param int $severity Severity level (1=info, 2=warning, 3=error)
     * @param int|null $errorCode Error code
     * @param string|null $objectType Object type
     * @param int|null $objectId Object ID
     * @param bool $allowDuplicate Allow duplicate messages
     * @param int|null $idEmployee Employee ID
     *
     * @return bool
     */
    public static function log(
        string $message,
        int $severity = self::INFO,
        ?int $errorCode = null,
        ?string $objectType = null,
        ?int $objectId = null,
        bool $allowDuplicate = true,
        ?int $idEmployee = null
    ): bool {
        if (!self::isEnabled()) {
            return false;
        }

        return \PrestaShopLogger::addLog(
            $message,
            $severity,
            $errorCode,
            $objectType,
            $objectId,
            $allowDuplicate,
            $idEmployee
        );
    }

    /**
     * Shortcut for info level logs
     *
     * @param string $message
     *
     * @return bool
     */
    public static function info(string $message): bool
    {
        return self::log($message, self::INFO);
    }

    /**
     * Shortcut for warning level logs
     *
     * @param string $message
     *
     * @return bool
     */
    public static function warning(string $message): bool
    {
        return self::log($message, self::WARNING);
    }

    /**
     * Shortcut for error level logs
     *
     * @param string $message
     *
     * @return bool
     */
    public static function error(string $message): bool
    {
        return self::log($message, self::ERROR);
    }

    /**
     * Check if logging is enabled
     *
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = (bool) \Configuration::get(GeneralDataConfiguration::ASKDIALOG_ENABLE_LOGS);
        }

        return self::$enabled;
    }

    /**
     * Reset cached enabled state (useful for testing or after config change)
     */
    public static function resetCache(): void
    {
        self::$enabled = null;
    }
}
