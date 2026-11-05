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

namespace Dialog\AskDialog\Traits;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Authenticates a front controller's caller by one of the module's API keys.
 *
 * Requires JsonResponseTrait. The key travels in `X-Api-Key` or in
 * `Authorization: Token <key>`; the `$_SERVER` fallbacks cover hosts (OVH,
 * FastCGI) that strip headers from getallheaders().
 */
trait ApiKeyAuthTrait
{
    /**
     * Answers 401 when no key is sent and 403 when it does not match the
     * configured one, then exits; returns only for a valid key.
     *
     * @param string $configurationKey ASKDIALOG_API_KEY or ASKDIALOG_API_KEY_PUBLIC
     * @param string $label Key name used in the error message
     */
    protected function requireApiKey($configurationKey, $label)
    {
        $token = $this->readApiToken();
        if ($token === null) {
            $this->sendJsonResponse(['error' => $label . ' API Token is missing'], 401);
        }

        $expected = (string) \Configuration::get($configurationKey);
        if ($expected === '' || !hash_equals($expected, $token)) {
            $this->sendJsonResponse(['error' => $label . ' API Token is wrong'], 403);
        }
    }

    /**
     * @return string|null
     */
    private function readApiToken()
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $candidates = [
            $this->getHeaderCaseInsensitive($headers, 'X-Api-Key'),
            $this->tokenFromAuthorization($this->getHeaderCaseInsensitive($headers, 'Authorization')),
            isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null,
            $this->tokenFromAuthorization(isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null),
            $this->tokenFromAuthorization(isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : null),
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param string|null $value
     *
     * @return string|null
     */
    private function tokenFromAuthorization($value)
    {
        if (!is_string($value) || strncmp($value, 'Token ', 6) !== 0) {
            return null;
        }

        return substr($value, 6);
    }
}
