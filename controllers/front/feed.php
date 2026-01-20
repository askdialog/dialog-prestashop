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

use Dialog\AskDialog\Form\GeneralDataConfiguration;
use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\ExportLogRepository;
use Dialog\AskDialog\Repository\ExportStateRepository;
use Dialog\AskDialog\Service\AskDialogClient;
use Dialog\AskDialog\Service\DataGenerator;
use Dialog\AskDialog\Service\Export\ProductExportService;
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
 * Supports resumable exports with polling for large catalogs
 */
class AskDialogFeedModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    /**
     * Safety margin in seconds before max_execution_time
     */
    private const TIME_SAFETY_MARGIN = 5;

    /**
     * Default time limit if max_execution_time is 0 (unlimited)
     */
    private const DEFAULT_TIME_LIMIT = 115;

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
     * Calculate safe time limit based on max_execution_time
     *
     * @return int Time limit in seconds
     */
    private function getSafeTimeLimit()
    {
        $maxExecutionTime = (int) ini_get('max_execution_time');

        if ($maxExecutionTime <= 0) {
            // Unlimited or CLI mode
            return self::DEFAULT_TIME_LIMIT;
        }

        $safeLimit = $maxExecutionTime - self::TIME_SAFETY_MARGIN;

        return max(5, $safeLimit); // Minimum 5 seconds
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

        switch ($action) {
            case 'sendCatalogData':
                $this->handleCatalogExport();
                break;

            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action',
                ], 400);
        }
    }

    /**
     * Handles catalog export with polling support
     * Can be called multiple times to resume an interrupted export
     */
    private function handleCatalogExport()
    {
        $maxExecutionTime = (int) ini_get('max_execution_time');
        PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: START (max_execution_time=' . $maxExecutionTime . 's)', 1);

        $stateRepo = new ExportStateRepository();
        $productExport = new ProductExportService();

        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;
        $countryCode = $this->context->country->iso_code;

        // Get batch size from configuration
        $configBatchSize = Configuration::get(GeneralDataConfiguration::ASKDIALOG_BATCH_SIZE);
        $batchSize = $configBatchSize !== false ? (int) $configBatchSize : GeneralDataConfiguration::DEFAULT_BATCH_SIZE;

        try {
            // Check for existing in-progress export
            $existingState = $stateRepo->findInProgress($idShop, ExportStateRepository::EXPORT_TYPE_CATALOG);

            if ($existingState) {
                // Resume existing export
                PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Resuming export ID=' . $existingState['id_export_state'] . ', offset=' . $existingState['products_exported'], 1);
                $this->processExportBatch($existingState, $stateRepo, $productExport, $batchSize);
            } else {
                // Start new export
                $totalProducts = $productExport->getProductCount($idShop);
                PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Starting new export, ' . $totalProducts . ' products', 1);

                if ($totalProducts === 0) {
                    $this->sendJsonResponse([
                        'status' => 'error',
                        'message' => 'No products found',
                    ], 400);

                    return;
                }

                // Create export state
                $stateId = $stateRepo->create(
                    $idShop,
                    ExportStateRepository::EXPORT_TYPE_CATALOG,
                    $totalProducts,
                    $batchSize,
                    $idLang,
                    $countryCode
                );

                if ($stateId === false) {
                    // Race condition: another export started, try to resume it
                    $existingState = $stateRepo->findInProgress($idShop, ExportStateRepository::EXPORT_TYPE_CATALOG);
                    if ($existingState) {
                        PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: Race condition, resuming existing export', 1);
                        $this->processExportBatch($existingState, $stateRepo, $productExport, $batchSize);

                        return;
                    }

                    throw new Exception('Failed to create export state');
                }

                $newState = $stateRepo->findById($stateId);
                $this->processExportBatch($newState, $stateRepo, $productExport, $batchSize);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('[AskDialog] Feed::handleCatalogExport: ERROR - ' . $e->getMessage(), 3);

            $this->sendJsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a batch of products within time limit
     *
     * @param array $state Current export state
     * @param ExportStateRepository $stateRepo State repository
     * @param ProductExportService $productExport Product export service
     * @param int $batchSize Batch size
     */
    private function processExportBatch($state, $stateRepo, $productExport, $batchSize)
    {
        $timeLimit = $this->getSafeTimeLimit();
        PrestaShopLogger::addLog('[AskDialog] Feed::processExportBatch: timeLimit=' . $timeLimit . 's', 1);

        $result = $productExport->processResumableBatch(
            (int) $state['id_shop'],
            (int) $state['id_lang'],
            $state['country_code'],
            (int) $state['products_exported'],
            $batchSize,
            $timeLimit,
            $state['tmp_file_path']
        );

        // Update state with progress
        $newProductsExported = (int) $state['products_exported'] + $result['productsProcessed'];
        $stateRepo->updateProgress(
            (int) $state['id_export_state'],
            $newProductsExported,
            $result['tmpFilePath']
        );

        if ($result['isComplete']) {
            // Export complete - finalize
            PrestaShopLogger::addLog('[AskDialog] Feed::processExportBatch: Export complete, finalizing...', 1);
            $this->finalizeExport($state, $stateRepo, $productExport, $result['tmpFilePath']);
        } else {
            // Export not complete - return progress for polling
            $progress = round(($newProductsExported / $result['totalProducts']) * 100);
            PrestaShopLogger::addLog('[AskDialog] Feed::processExportBatch: Progress ' . $progress . '% (' . $newProductsExported . '/' . $result['totalProducts'] . ')', 1);

            $this->sendJsonResponse([
                'status' => 'in_progress',
                'stepDone' => false,
                'progress' => $progress,
                'productsExported' => $newProductsExported,
                'totalProducts' => $result['totalProducts'],
                'message' => $newProductsExported . '/' . $result['totalProducts'] . ' products exported',
            ], 200);
        }
    }

    /**
     * Finalize export: convert NDJSON, generate CMS, upload to S3
     *
     * @param array $state Export state
     * @param ExportStateRepository $stateRepo State repository
     * @param ProductExportService $productExport Product export service
     * @param string $ndjsonFilePath Path to NDJSON file
     */
    private function finalizeExport($state, $stateRepo, $productExport, $ndjsonFilePath)
    {
        $exportLogRepo = new ExportLogRepository();
        $dataGenerator = new DataGenerator();

        $idShop = (int) $state['id_shop'];
        $idLang = (int) $state['id_lang'];

        // Create export log entry
        $exportLogId = $exportLogRepo->createLog(
            $idShop,
            ExportLogRepository::EXPORT_TYPE_CATALOG,
            [
                'id_lang' => $idLang,
                'country_code' => $state['country_code'],
                'total_products' => $state['total_products'],
            ]
        );
        $exportLogRepo->updateStatus($exportLogId, ExportLogRepository::STATUS_PENDING);

        try {
            // Convert NDJSON to final JSON
            PrestaShopLogger::addLog('[AskDialog] Feed::finalizeExport: Converting NDJSON to JSON...', 1);
            $catalogFile = $productExport->convertNdjsonToJson($ndjsonFilePath);

            // Generate CMS pages
            PrestaShopLogger::addLog('[AskDialog] Feed::finalizeExport: Generating CMS data...', 1);
            $cmsFile = $dataGenerator->generateCMSData($idLang);

            // Upload to S3
            PrestaShopLogger::addLog('[AskDialog] Feed::finalizeExport: Uploading to S3...', 1);
            $this->uploadToS3($catalogFile, $cmsFile, $exportLogId, $exportLogRepo);

            // Mark state as completed and delete it
            $stateRepo->markCompleted((int) $state['id_export_state']);
            $stateRepo->delete((int) $state['id_export_state']);

            PrestaShopLogger::addLog('[AskDialog] Feed::finalizeExport: SUCCESS', 1);

            $this->sendJsonResponse([
                'status' => 'success',
                'stepDone' => true,
                'progress' => 100,
                'message' => 'Export completed and uploaded to S3',
            ], 200);
        } catch (Exception $e) {
            // Mark export as failed
            $stateRepo->markFailed((int) $state['id_export_state']);
            $exportLogRepo->updateStatus(
                $exportLogId,
                ExportLogRepository::STATUS_ERROR,
                ['error_message' => $e->getMessage()]
            );

            PrestaShopLogger::addLog('[AskDialog] Feed::finalizeExport: ERROR - ' . $e->getMessage(), 3);

            $this->sendJsonResponse([
                'status' => 'error',
                'stepDone' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Uploads catalog and CMS files to S3
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
        $askDialogClient = new AskDialogClient();
        $result = $askDialogClient->prepareServerTransfer();

        if ($result['statusCode'] !== 200) {
            throw new Exception('Failed to get S3 upload URLs: ' . $result['body']);
        }

        $uploadUrls = json_decode($result['body'], true);
        if ($uploadUrls === null) {
            throw new Exception('Invalid response from prepareServerTransfer');
        }

        if (!isset($uploadUrls['catalogUploadUrl']) || !isset($uploadUrls['pageUploadUrl'])) {
            throw new Exception('Dialog API missing required upload URLs');
        }

        $bodyCatalog = $uploadUrls['catalogUploadUrl'];
        $bodyPages = $uploadUrls['pageUploadUrl'];

        try {
            // Send catalog to S3
            $urlCatalog = $bodyCatalog['url'];
            $fieldsCatalog = $bodyCatalog['fields'];
            $catalogFilename = basename($catalogFile);
            $catalogSize = round(filesize($catalogFile) / 1024 / 1024, 2);
            PrestaShopLogger::addLog('[AskDialog] S3Upload: Uploading catalog (' . $catalogSize . 'MB)...', 1);
            $responseCatalog = $this->sendFileToS3($urlCatalog, $fieldsCatalog, $catalogFile, $catalogFilename);

            // Send CMS pages to S3
            $urlPages = $bodyPages['url'];
            $fieldsPages = $bodyPages['fields'];
            $cmsFilename = basename($cmsFile);
            PrestaShopLogger::addLog('[AskDialog] S3Upload: Uploading CMS pages...', 1);
            $responsePages = $this->sendFileToS3($urlPages, $fieldsPages, $cmsFile, $cmsFilename);

            // Check all uploads succeeded
            if ($responseCatalog->getStatusCode() === 204 && $responsePages->getStatusCode() === 204) {
                PrestaShopLogger::addLog('[AskDialog] S3Upload: Both uploads successful', 1);

                // Move files to sent folder
                rename($catalogFile, PathHelper::getSentDir() . $catalogFilename);
                rename($cmsFile, PathHelper::getSentDir() . $cmsFilename);

                // Update export log
                $exportLogRepo->updateStatus(
                    $exportLogId,
                    ExportLogRepository::STATUS_SUCCESS,
                    [
                        'file_name' => $catalogFilename,
                        's3_url' => $urlCatalog,
                    ]
                );

                // Cleanup old files
                PathHelper::cleanTmpFiles(86400);
                PathHelper::cleanSentFilesKeepRecent(20);
            } else {
                throw new Exception('S3 upload failed - unexpected status code');
            }
        } catch (HttpExceptionInterface $e) {
            throw new Exception('HTTP error during S3 upload: ' . $e->getMessage());
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Network error during S3 upload: ' . $e->getMessage());
        }

        PrestaShopLogger::addLog('[AskDialog] S3Upload: SUCCESS', 1);
    }
}
