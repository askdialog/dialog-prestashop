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

use Dialog\AskDialog\Service\DataGenerator;
use Dialog\AskDialog\Service\AskDialogClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Class AskDialogFeedModuleFrontController
 * 
 * Handles catalog data export and upload to Dialog AI platform via S3
 */
class AskDialogFeedModuleFrontController extends ModuleFrontController
{
    /**
     * Initialize controller and verify API key authentication
     */
    public function initContent()
    {
        parent::initContent();
        
        // Check if token is valid
        $headers = getallheaders();
        
        if (!isset($headers['Authorization']) || substr($headers['Authorization'], 0, 6) !== 'Token ') {
            $this->sendJsonResponse(['error' => 'Private API Token is missing'], 401);
        }
        
        if ($headers['Authorization'] !== 'Token ' . Configuration::get('ASKDIALOG_API_KEY')) {
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
     * @return \Symfony\Contracts\HttpClient\ResponseInterface
     * @throws TransportExceptionInterface
     */
    private function sendFileToS3($url, $fields, $tempFile, $filename)
    {
        $httpClient = HttpClient::create(['verify_peer' => false]);
        
        // Prepare multipart data
        $multipartData = [];
        
        // Add S3 fields
        foreach ($fields as $name => $contents) {
            $multipartData[] = [
                'name' => $name,
                'contents' => $contents,
            ];
        }
        
        // Add file
        $multipartData[] = [
            'name' => 'file',
            'contents' => fopen($tempFile, 'r'),
            'filename' => $filename,
        ];
        
        // Add Content-Type
        $multipartData[] = [
            'name' => 'Content-Type',
            'contents' => 'application/json',
        ];
        
        return $httpClient->request('POST', $url, [
            'body' => $multipartData,
        ]);
    }

    /**
     * Main AJAX handler for feed actions
     */
    public function displayAjax()
    {
        $action = Tools::getValue('action');
        $dataGenerator = new DataGenerator();
        $batchSize = Configuration::get('ASKDIALOG_BATCH_SIZE');

        switch ($action) {
            case 'sendCatalogData':
                $this->handleCatalogExport($dataGenerator, $batchSize);
                break;
                
            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action'
                ], 400);
        }
    }

    /**
     * Handles catalog export: generate data, upload to S3
     *
     * @param DataGenerator $dataGenerator
     * @param int $batchSize
     */
    private function handleCatalogExport($dataGenerator, $batchSize)
    {
        try {
            $idShop = Configuration::get('PS_SHOP_DEFAULT');
            $numRemaining = $dataGenerator->getNumCatalogRemaining($idShop);
            $dataCatalog = $dataGenerator->getCatalogDataForBatch($batchSize, $idShop);

            // Process partial batch if needed
            if ($numRemaining > 0 && $numRemaining <= $batchSize) {
                $this->generatePartialDataFile($dataCatalog, $dataGenerator, $idShop);
                $numRemaining = $dataGenerator->getNumCatalogRemaining($idShop);
            }

            // If still items remaining, generate another partial file and return
            if ($numRemaining > 0) {
                $this->generatePartialDataFile($dataCatalog, $dataGenerator, $idShop);
                $this->sendJsonResponse([
                    'status' => 'success',
                    'message' => 'Partial data generated',
                    'remaining' => $numRemaining
                ]);
            }

            // All products processed - merge partial files
            $files = glob(_PS_MODULE_DIR_ . 'askdialog/temp/catalog_partial_*.json');
            
            if (empty($files)) {
                throw new Exception('No catalog files found to process.');
            }

            // Merge all partial files
            $dataCatalog = [];
            foreach ($files as $file) {
                $partialData = json_decode(file_get_contents($file), true);
                if ($partialData === null) {
                    throw new Exception('Invalid JSON in file: ' . basename($file));
                }
                $dataCatalog = array_merge($dataCatalog, $partialData);
            }

            // Clean up partial files
            array_map('unlink', $files);

            // Generate final catalog file
            $filename = 'catalog_' . date('Ymd_His') . '.json';
            $tempFile = _PS_MODULE_DIR_ . 'askdialog/temp/' . $filename;
            file_put_contents($tempFile, json_encode($dataCatalog));

            // Reset queue for next export
            $this->resetExportQueue($idShop);

            // Generate CMS pages export
            $dataGenerator->generateCMSData();

            // Upload to S3
            $this->uploadToS3($tempFile, $filename);

        } catch (Exception $e) {
            $this->sendJsonResponse([
                'status' => 'error',
                'message' => 'Exception while exporting data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Uploads catalog and CMS files to S3
     *
     * @param string $tempFile Path to catalog JSON file
     * @param string $filename Catalog filename
     * @throws Exception
     */
    private function uploadToS3($tempFile, $filename)
    {
        // Get signed URLs from Dialog API
        $askDialogClient = new AskDialogClient(Configuration::get('ASKDIALOG_API_KEY'));
        $result = $askDialogClient->prepareServerTransfer();
        
        if ($result['statusCode'] !== 200) {
            throw new Exception('Failed to get S3 upload URLs: ' . $result['body']);
        }
        
        $uploadUrls = json_decode($result['body'], true);
        if ($uploadUrls === null) {
            throw new Exception('Invalid response from prepareServerTransfer');
        }

        $bodyCatalog = $uploadUrls['catalogUploadUrl'];
        $bodyPages = $uploadUrls['pageUploadUrl'];

        try {
            // Send catalog to S3
            $urlCatalog = $bodyCatalog['url'];
            $fieldsCatalog = $bodyCatalog['fields'];
            $responseCatalog = $this->sendFileToS3($urlCatalog, $fieldsCatalog, $tempFile, $filename);

            // Send CMS pages to S3
            $urlPages = $bodyPages['url'];
            $fieldsPages = $bodyPages['fields'];
            $tempsCmsFile = _PS_MODULE_DIR_ . 'askdialog/temp/cms.json';
            $responsePages = $this->sendFileToS3($urlPages, $fieldsPages, $tempsCmsFile, 'cms.json');

            // Check both uploads succeeded
            if ($responseCatalog->getStatusCode() === 204 && $responsePages->getStatusCode() === 204) {
                // Move files to sent folder
                rename($tempFile, _PS_MODULE_DIR_ . 'askdialog/sent/' . $filename);
                $filenameCMS = 'cms_' . date('Ymd_His') . '.json';
                rename($tempsCmsFile, _PS_MODULE_DIR_ . 'askdialog/sent/' . $filenameCMS);

                $this->sendJsonResponse([
                    'status' => 'success',
                    'message' => 'Catalog and Pages data sent successfully'
                ]);
            } else {
                throw new Exception('S3 upload failed - unexpected status code');
            }

        } catch (HttpExceptionInterface $e) {
            throw new Exception('HTTP error during S3 upload: ' . $e->getMessage());
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Network error during S3 upload: ' . $e->getMessage());
        }
    }

    /**
     * Generates a partial catalog file and removes processed products from queue
     *
     * @param array $dataCatalog Product data to save
     * @param DataGenerator $dataGenerator
     * @param int $idShop
     */
    private function generatePartialDataFile($dataCatalog, $dataGenerator, $idShop)
    {
        $filename = 'catalog_partial_' . date('Ymd_His') . '_' . uniqid() . '.json';
        $tempFile = _PS_MODULE_DIR_ . 'askdialog/temp/' . $filename;
        file_put_contents($tempFile, json_encode($dataCatalog));

        // Remove processed products from queue
        $productIds = array_column($dataCatalog, 'id');
        if (!empty($productIds)) {
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'askdialog_product 
                    WHERE id_shop = ' . (int)$idShop . ' 
                    AND id_product IN (' . implode(',', array_map('intval', $productIds)) . ')';
            Db::getInstance()->execute($sql);
        }
    }

    /**
     * Resets the export queue by repopulating with all products
     *
     * @param int $idShop
     */
    private function resetExportQueue($idShop)
    {
        // Truncate queue
        $sql = 'TRUNCATE TABLE ' . _DB_PREFIX_ . 'askdialog_product';
        Db::getInstance()->execute($sql);

        // Repopulate with all products
        $products = Db::getInstance()->executeS('SELECT id_product FROM ' . _DB_PREFIX_ . 'product');
        foreach ($products as $product) {
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'askdialog_product (id_product, id_shop) 
                    VALUES (' . (int)$product['id_product'] . ', ' . (int)$idShop . ')';
            Db::getInstance()->execute($sql);
        }
    }

    /**
     * Sends a JSON response with proper headers and exits
     *
     * @param array $data Response data
     * @param int $statusCode HTTP status code (default: 200)
     */
    private function sendJsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
