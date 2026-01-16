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

namespace Dialog\AskDialog\Trait;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Trait JsonResponseTrait
 *
 * Provides JSON response functionality for front controllers
 * Used to send API responses with proper HTTP headers and status codes
 */
trait JsonResponseTrait
{
    /**
     * Sends a JSON response with proper headers and exits
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code (default: 200)
     *
     * @return void
     */
    protected function sendJsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Sends a JSON response without terminating script execution
     * Useful for async operations where processing continues after response is sent
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code (default: 200)
     *
     * @return void
     */
    protected function sendJsonResponseAsync($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Flush output buffers to send response immediately
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        // Immediate response if PHP-FPM is available
        // Falls back gracefully on non-FPM environments (mod_php, CGI)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Get HTTP header value case-insensitively
     *
     * HTTP header names are case-insensitive per RFC 7230
     * This method normalizes header lookup to handle any casing
     *
     * @param array $headers Array of headers from getallheaders()
     * @param string $headerName Header name to search for
     *
     * @return string|null Header value or null if not found
     */
    protected function getHeaderCaseInsensitive($headers, $headerName)
    {
        $headerNameLower = strtolower($headerName);
        foreach ($headers as $name => $value) {
            if (strtolower($name) === $headerNameLower) {
                return $value;
            }
        }

        return null;
    }
}
