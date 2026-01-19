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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\ExportLogRepository;
use Dialog\AskDialog\Service\AskDialogClient;
use Dialog\AskDialog\Service\DataGenerator;
use Dialog\AskDialog\Traits\JsonResponseTrait;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class AskDialogFeedModuleFrontController
 *
 * Handles catalog data export and upload to Dialog AI platform via S3
 */
class AskDialogFeedModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    /**
     * Batch size for product export (5000 products per batch)
     */
    private const BATCH_SIZE = 5000;

    /**
     * Initialize controller and verify API key authentication
     */
    public function initContent()
    {
        parent::initContent();

        // Check if token is valid
        $headers = getallheaders();
        $authHeader = $this->getHeaderCaseInsensitive($headers, 'Authorization');

        if ($authHeader === null || substr($authHeader, 0, 6) !== 'Token ') {
            $this->sendJsonResponse(['error' => 'Private API Token is missing'], 401);
        }

        if ($authHeader !== 'Token ' . Configuration::get('ASKDIALOG_API_KEY')) {
            $this->sendJsonResponse(['error' => 'Private API Token is wrong'], 403);
        }

        $this->ajax = true;
    }

    /**
     * Sends a file to S3 using signed URL with multipart/form-data
     *
     * @param string $url S3 signed URL
     * @param array $fields Additional form fields (from S3 signature)
     * @param string $tempFile Path to file to upload
     * @param string $filename Original filename
     *
     * @return Symfony\Contracts\HttpClient\ResponseInterface
     *
     * @throws TransportExceptionInterface
     */
    private function sendFileToS3($url, $fields, $tempFile, $filename)
    {
        $httpClient = HttpClient::create(['verify_peer' => false]);

        // Build form fields
        $formFields = $fields;

        // Add explicit Content-Type field for S3 policy validation
        $formFields['Content-Type'] = 'application/json';

        // Add file with application/json Content-Type
        $formFields['file'] = DataPart::fromPath($tempFile, $filename, 'application/json');

        // Create multipart form
        $formData = new FormDataPart($formFields);
        $headers = $formData->getPreparedHeaders()->toArray();

        return $httpClient->request('POST', $url, [
            'headers' => $headers,
            'body' => $formData->bodyToString(),
        ]);
    }

    /**
     * Main AJAX handler for feed actions
     */
    public function displayAjax()
    {
        $action = Tools::getValue('action');
        $dataGenerator = new DataGenerator();

        switch ($action) {
            case 'sendCatalogData':
                $this->handleCatalogExport($dataGenerator);
                break;

            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action',
                ], 400);
        }
    }

    /**
     * Handles catalog export: generate data, upload to S3
     * Uses batch processing for memory efficiency on large catalogs
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleCatalogExport($dataGenerator)
    {
        PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: START', 1);

        // Send immediate async response to Dialog API (HTTP 202 Accepted)
        // Response sent immediately, then processing continues in background
        $this->sendJsonResponseAsync([
            'status' => 'accepted',
            'message' => 'Export started',
        ], 202);

        PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Async response 202 sent to client', 1);

        $exportLogRepo = new ExportLogRepository();
        $exportLogId = null;

        // Process export asynchronously (client already received 202)
        try {
            $idShop = (int) $this->context->shop->id;
            $idLang = (int) $this->context->language->id;
            $countryCode = $this->context->country->iso_code;

            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Context - idShop=' . $idShop . ', idLang=' . $idLang . ', countryCode=' . $countryCode, 1);

            // Get product count for batch calculation
            $totalProducts = $dataGenerator->getProductCount($idShop);
            $totalBatches = (int) ceil($totalProducts / self::BATCH_SIZE);
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: ' . $totalProducts . ' products, ' . $totalBatches . ' batches', 1);

            // Create export log with 'init' status and batch info
            $exportLogId = $exportLogRepo->createLog(
                $idShop,
                ExportLogRepository::EXPORT_TYPE_CATALOG,
                [
                    'id_lang' => $idLang,
                    'country_code' => $countryCode,
                    'batch_size' => self::BATCH_SIZE,
                    'total_products' => $totalProducts,
                    'total_batches' => $totalBatches,
                    'batches_completed' => 0,
                    'current_batch' => 0,
                ]
            );
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Export log created, ID=' . $exportLogId, 1);

            // Update status to 'pending' - starting generation
            $exportLogRepo->updateStatus($exportLogId, ExportLogRepository::STATUS_PENDING);

            // Progress callback to update metadata after each batch
            $progressCallback = function ($batchCompleted, $totalBatches) use ($exportLogRepo, $exportLogId) {
                $exportLogRepo->updateMetadata($exportLogId, [
                    'batches_completed' => $batchCompleted,
                    'current_batch' => $batchCompleted,
                ]);
                PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Progress ' . $batchCompleted . '/' . $totalBatches, 1);
            };

            // Generate catalog data with batch processing
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Generating catalog data (batched)...', 1);
            $catalogFile = $dataGenerator->generateCatalogDataBatched($idShop, $idLang, $countryCode, self::BATCH_SIZE, $progressCallback);
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Catalog file generated: ' . $catalogFile, 1);

            // Generate CMS pages export (Service handles everything)
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Generating CMS data...', 1);
            $cmsFile = $dataGenerator->generateCMSData($idLang);
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: CMS file generated: ' . $cmsFile, 1);

            // Upload files to S3 (this will update log on success)
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Starting S3 upload...', 1);
            $this->uploadToS3($catalogFile, $cmsFile, $exportLogId, $exportLogRepo);

            // Log success
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: SUCCESS - Export completed', 1);
        } catch (Exception $e) {
            // Update export log with error status
            if ($exportLogId) {
                $exportLogRepo->updateStatus(
                    $exportLogId,
                    ExportLogRepository::STATUS_ERROR,
                    ['error_message' => $e->getMessage()]
                );
            }

            // Log error since client already received 202 response
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: ERROR - ' . $e->getMessage(), 3);
        }
    }

    /**
     * Uploads catalog (with embedded categories) and CMS files to S3
     *
     * @param string $catalogFile Path to catalog JSON file
     * @param string $cmsFile Path to CMS JSON file
     * @param int $exportLogId Export log ID for tracking
     * @param ExportLogRepository $exportLogRepo Repository for updating log
     *
     * @throws Exception
     */
    private function uploadToS3($catalogFile, $cmsFile, $exportLogId, $exportLogRepo)
    {
        PrestaShopLogger::addLog('[AskDialog] S3Upload: START', 1);

        // Get signed URLs from Dialog API
        PrestaShopLogger::addLog('[AskDialog] S3Upload: Requesting signed URLs from Dialog API...', 1);
        $askDialogClient = new AskDialogClient();
        $result = $askDialogClient->prepareServerTransfer();

        if ($result['statusCode'] !== 200) {
            PrestaShopLogger::addLog('[AskDialog] S3Upload: ERROR - Failed to get signed URLs, statusCode=' . $result['statusCode'], 3);
            throw new Exception('Failed to get S3 upload URLs: ' . $result['body']);
        }

        PrestaShopLogger::addLog('[AskDialog] S3Upload: Signed URLs received successfully', 1);

        $uploadUrls = json_decode($result['body'], true);
        if ($uploadUrls === null) {
            PrestaShopLogger::addLog('[AskDialog] S3Upload: ERROR - Invalid JSON response from prepareServerTransfer', 3);
            throw new Exception('Invalid response from prepareServerTransfer');
        }

        // Extract upload URLs (validate presence)
        if (!isset($uploadUrls['catalogUploadUrl']) || !isset($uploadUrls['pageUploadUrl'])) {
            PrestaShopLogger::addLog('[AskDialog] S3Upload: ERROR - Missing upload URLs in API response', 3);
            throw new Exception('Dialog API missing required upload URLs. Expected: catalogUploadUrl, pageUploadUrl');
        }

        $bodyCatalog = $uploadUrls['catalogUploadUrl'];
        $bodyPages = $uploadUrls['pageUploadUrl'];

        try {
            // Send catalog to S3
            $urlCatalog = $bodyCatalog['url'];
            $fieldsCatalog = $bodyCatalog['fields'];
            $catalogFilename = basename($catalogFile);
            $catalogSize = round(filesize($catalogFile) / 1024 / 1024, 2);
            PrestaShopLogger::addLog('[AskDialog] S3Upload: Uploading catalog (' . $catalogSize . 'MB) to S3...', 1);
            $responseCatalog = $this->sendFileToS3($urlCatalog, $fieldsCatalog, $catalogFile, $catalogFilename);
            PrestaShopLogger::addLog('[AskDialog] S3Upload: Catalog upload response: HTTP ' . $responseCatalog->getStatusCode(), 1);

            // Send CMS pages to S3
            $urlPages = $bodyPages['url'];
            $fieldsPages = $bodyPages['fields'];
            $cmsFilename = basename($cmsFile);
            $cmsSize = round(filesize($cmsFile) / 1024, 2);
            PrestaShopLogger::addLog('[AskDialog] S3Upload: Uploading CMS pages (' . $cmsSize . 'KB) to S3...', 1);
            $responsePages = $this->sendFileToS3($urlPages, $fieldsPages, $cmsFile, $cmsFilename);
            PrestaShopLogger::addLog('[AskDialog] S3Upload: CMS upload response: HTTP ' . $responsePages->getStatusCode(), 1);

            // Check all uploads succeeded
            if ($responseCatalog->getStatusCode() === 204
                && $responsePages->getStatusCode() === 204) {
                PrestaShopLogger::addLog('[AskDialog] S3Upload: Both uploads successful (HTTP 204)', 1);

                // Move files to sent folder
                rename($catalogFile, PathHelper::getSentDir() . $catalogFilename);
                rename($cmsFile, PathHelper::getSentDir() . $cmsFilename);
                PrestaShopLogger::addLog('[AskDialog] S3Upload: Files moved to sent/ folder', 1);

                // Update export log with success status
                $exportLogRepo->updateStatus(
                    $exportLogId,
                    ExportLogRepository::STATUS_SUCCESS,
                    [
                        'file_name' => $catalogFilename,
                        's3_url' => $urlCatalog, // Base S3 URL (bucket path)
                    ]
                );

                // Clean up old files after successful export
                PathHelper::cleanTmpFiles(86400);
                PathHelper::cleanSentFilesKeepRecent(20);
                PrestaShopLogger::addLog('[AskDialog] S3Upload: Cleanup completed', 1);
            } else {
                PrestaShopLogger::addLog('[AskDialog] S3Upload: ERROR - Unexpected status codes: catalog=' . $responseCatalog->getStatusCode() . ', cms=' . $responsePages->getStatusCode(), 3);
                throw new Exception('S3 upload failed - unexpected status code');
            }
        } catch (HttpExceptionInterface $e) {
            PrestaShopLogger::addLog('[AskDialog] S3Upload: ERROR - HTTP exception: ' . $e->getMessage(), 3);
            throw new Exception('HTTP error during S3 upload: ' . $e->getMessage());
        } catch (TransportExceptionInterface $e) {
            PrestaShopLogger::addLog('[AskDialog] S3Upload: ERROR - Transport exception: ' . $e->getMessage(), 3);
            throw new Exception('Network error during S3 upload: ' . $e->getMessage());
        }

        PrestaShopLogger::addLog('[AskDialog] S3Upload: SUCCESS', 1);
    }
}
