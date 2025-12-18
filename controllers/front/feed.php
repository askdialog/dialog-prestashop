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
use Dialog\AskDialog\Helper\PathHelper;
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

        // Build form fields
        $formFields = $fields;
        $formFields['file'] = DataPart::fromPath($tempFile, $filename);

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
                    'message' => 'Invalid action'
                ], 400);
        }
    }

    /**
     * Handles catalog export: generate data, upload to S3
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleCatalogExport($dataGenerator)
    {
        try {
            $idShop = (int)$this->context->shop->id;
            $idLang = (int)$this->context->language->id;
            $countryCode = $this->context->country->iso_code;

            // Get all product IDs for current shop
            $productIds = $this->getProductIdsForShop($idShop);

            if (empty($productIds)) {
                throw new Exception('No products found for shop ID ' . $idShop);
            }

            // Generate catalog data
            $catalogData = [];
            $linkObj = new Link();

            foreach ($productIds as $productId) {
                $productData = $dataGenerator->getProductData($productId, $idLang, $linkObj, $countryCode);
                if (!empty($productData)) {
                    $catalogData[] = $productData;
                }
            }

            if (empty($catalogData)) {
                throw new Exception('No valid product data generated');
            }

            // Generate final catalog file
            $filename = 'catalog_' . date('Ymd_His') . '.json';
            $tempFile = PathHelper::getTmpDir() . $filename;
            file_put_contents($tempFile, json_encode($catalogData));

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
     * Get all product IDs for a specific shop
     *
     * TODO: Move to a Repository class in future refactoring
     *
     * @param int $idShop Shop ID
     * @return array Array of product IDs
     */
    private function getProductIdsForShop($idShop)
    {
        $sql = 'SELECT p.id_product
                FROM ' . _DB_PREFIX_ . 'product p
                INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product
                WHERE ps.id_shop = ' . (int)$idShop;

        $results = Db::getInstance()->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'id_product');
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
        $askDialogClient = new AskDialogClient();
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
            $cmsFile = PathHelper::getTmpDir() . 'cms.json';
            $responsePages = $this->sendFileToS3($urlPages, $fieldsPages, $cmsFile, 'cms.json');

            // Check both uploads succeeded
            if ($responseCatalog->getStatusCode() === 204 && $responsePages->getStatusCode() === 204) {
                // Move files to sent folder
                rename($tempFile, PathHelper::getSentDir() . $filename);
                $filenameCMS = 'cms_' . date('Ymd_His') . '.json';
                rename($cmsFile, PathHelper::getSentDir() . $filenameCMS);

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
