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

namespace Dialog\AskDialog\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class AskDialogClient
 *
 * Handles HTTP communication with Dialog AI platform API
 *
 * @package Dialog\AskDialog\Service
 */
class AskDialogClient
{
    /**
     * @var HttpClientInterface Symfony HTTP client instance
     */
    private $httpClient;

    /**
     * AskDialogClient constructor.
     *
     * @param string $apiKey Private API key for authentication
     */
    public function __construct(string $apiKey)
    {
        $apiUrl = \Configuration::get('ASKDIALOG_API_URL');

        if (empty($apiUrl)) {
            throw new \Exception('ASKDIALOG_API_URL configuration is missing');
        }

        $this->httpClient = HttpClient::create([
            'base_uri' => $apiUrl,
            'headers' => [
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Sends domain and PrestaShop version to Dialog API for validation
     *
     * @return array Response containing statusCode and body
     *               ['statusCode' => int, 'body' => string]
     */
    public function sendDomainHost(): array
    {
        $body = [
            'domain' => \Context::getContext()->shop->domain,
            'version' => _PS_VERSION_,
        ];

        try {
            $response = $this->httpClient->request('POST', '/organization/validate', [
                'json' => $body,
            ]);

            return [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ];
        } catch (HttpExceptionInterface $e) {
            return [
                'statusCode' => $e->getResponse()->getStatusCode(),
                'body' => $e->getMessage(),
            ];
        } catch (TransportExceptionInterface $e) {
            return [
                'statusCode' => 500,
                'body' => 'Transport error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Requests signed S3 upload URLs from Dialog API for catalog transfer
     *
     * @return array Response containing statusCode and body with upload URLs
     *               ['statusCode' => int, 'body' => string (JSON)]
     */
    public function prepareServerTransfer(): array
    {
        $body = [
            'fileType' => 'catalog',
        ];

        try {
            $response = $this->httpClient->request('POST', '/organization/catalog-upload-url', [
                'json' => $body,
            ]);

            return [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ];
        } catch (HttpExceptionInterface $e) {
            return [
                'statusCode' => $e->getResponse()->getStatusCode(),
                'body' => $e->getMessage(),
            ];
        } catch (TransportExceptionInterface $e) {
            return [
                'statusCode' => 500,
                'body' => 'Transport error: ' . $e->getMessage(),
            ];
        }
    }
}
