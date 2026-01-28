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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class AskDialogClient
 *
 * Handles HTTP communication with Dialog AI platform API
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
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     *
     * @throws \Exception If file not found
     * @throws TransportExceptionInterface
     */
    public function uploadFileToS3($url, array $fields, $filePath, $filename)
    {
        if (!file_exists($filePath)) {
            Logger::error('[AskDialog] AskDialogClient::uploadFileToS3: File not found: ' . $filePath);
            throw new \Exception('File not found: ' . $filePath);
        }

        $fileSize = PathHelper::formatFileSize(filesize($filePath));
        Logger::info('[AskDialog] AskDialogClient::uploadFileToS3: Uploading ' . $filename . ' (' . $fileSize . ')...');

        // Use a separate client for S3 (no base_uri, no auth headers)
        $s3Client = HttpClient::create(['verify_peer' => false]);

        // Build form fields
        $formFields = $fields;

        // Add explicit Content-Type field for S3 policy validation
        $formFields['Content-Type'] = 'application/json';

        // Add file with application/json Content-Type
        $formFields['file'] = DataPart::fromPath($filePath, $filename, 'application/json');

        // Create multipart form
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();

        $response = $s3Client->request('POST', $url, [
            'headers' => $headers,
            'body' => $formData->bodyToString(),
        ]);

        Logger::info('[AskDialog] AskDialogClient::uploadFileToS3: Upload complete, status=' . $response->getStatusCode());

        return $response;
    }
}
