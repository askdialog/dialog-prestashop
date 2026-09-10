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

namespace Dialog\AskDialog\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\Logger;
use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Service\Http\HttpResponse;
use Dialog\AskDialog\Service\Http\HttpTransport;
use Dialog\AskDialog\Service\Http\HttpTransportException;

/**
 * Class AskDialogClient
 *
 * Handles HTTP communication with Dialog AI platform API
 */
class AskDialogClient
{
    /**
     * @var HttpTransport HTTP transport (Symfony HttpClient or cURL)
     */
    private $httpClient;

    /**
     * AskDialogClient constructor.
     *
     * @throws \Exception If ASKDIALOG_API_KEY or ASKDIALOG_API_URL is not configured
     */
    public function __construct()
    {
        $apiKey = \Configuration::get('ASKDIALOG_API_KEY');
        if (empty($apiKey)) {
            throw new \Exception('ASKDIALOG_API_KEY configuration is missing. Please configure the module.');
        }

        $apiUrl = \Configuration::get('ASKDIALOG_API_URL');
        if (empty($apiUrl)) {
            throw new \Exception('ASKDIALOG_API_URL configuration is missing. Please reinstall the module.');
        }

        $this->httpClient = new HttpTransport([
            'base_uri' => $apiUrl,
            'headers' => [
                'Authorization' => $apiKey,
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
            $response = $this->httpClient->postJson('/organization/validate', $body);

            // An HTTP error status is part of the answer, not an exception:
            // callers already branch on statusCode.
            return [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ];
        } catch (HttpTransportException $e) {
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
            $response = $this->httpClient->postJson('/organization/catalog-upload-url', $body);

            // An HTTP error status is part of the answer, not an exception:
            // callers already branch on statusCode.
            return [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ];
        } catch (HttpTransportException $e) {
            return [
                'statusCode' => 500,
                'body' => 'Transport error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Upload a file to S3 using a signed URL with multipart/form-data
     *
     * Uses a separate HttpClient instance (not the base_uri one)
     * since S3 URLs are absolute and external.
     *
     * @param string $url S3 signed URL
     * @param array $fields Form fields from S3 signature (policy, key, etc.)
     * @param string $filePath Absolute path to the file to upload
     * @param string $filename Filename to send to S3
     *
     * @return HttpResponse
     *
     * @throws \Exception If file not found
     * @throws HttpTransportException
     */
    public function uploadFileToS3($url, array $fields, $filePath, $filename)
    {
        if (!file_exists($filePath)) {
            Logger::error('[AskDialog] AskDialogClient::uploadFileToS3: File not found: ' . $filePath);
            throw new \Exception('File not found: ' . $filePath);
        }

        $fileSize = PathHelper::formatFileSize(filesize($filePath));
        Logger::info('[AskDialog] AskDialogClient::uploadFileToS3: Uploading ' . $filename . ' (' . $fileSize . ')...');

        // Build form fields
        $formFields = $fields;

        // Add explicit Content-Type field for S3 policy validation
        $formFields['Content-Type'] = 'application/json';

        // Separate transport for S3: no base_uri, no auth headers. The file is
        // appended last by postMultipart, as the S3 POST policy requires.
        // TLS verification stays on: these uploads carry the merchant's
        // catalogue over a signed HTTPS URL, and S3 presents a valid
        // certificate — the module's other calls have always verified it.
        $s3Transport = new HttpTransport(['timeout' => 30]);

        $response = $s3Transport->postMultipart($url, $formFields, $filePath, $filename, 'application/json');

        Logger::info('[AskDialog] AskDialogClient::uploadFileToS3: Upload complete, status=' . $response->getStatusCode());

        return $response;
    }
}
