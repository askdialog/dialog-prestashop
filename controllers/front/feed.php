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
use Dialog\AskDialog\Trait\JsonResponseTrait;
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

            // Generate catalog data (Service handles everything)
            $catalogFile = $dataGenerator->generateCatalogData($idShop, $idLang, $countryCode);

            // Generate CMS pages export (Service handles everything)
            $cmsFile = $dataGenerator->generateCMSData($idLang);

            // Upload both files to S3
            $this->uploadToS3($catalogFile, $cmsFile);

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
     * @param string $catalogFile Path to catalog JSON file
     * @param string $cmsFile Path to CMS JSON file
     * @throws Exception
     */
    private function uploadToS3($catalogFile, $cmsFile)
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
            $catalogFilename = basename($catalogFile);
            $responseCatalog = $this->sendFileToS3($urlCatalog, $fieldsCatalog, $catalogFile, $catalogFilename);

            // Send CMS pages to S3
            $urlPages = $bodyPages['url'];
            $fieldsPages = $bodyPages['fields'];
            $cmsFilename = basename($cmsFile);
            $responsePages = $this->sendFileToS3($urlPages, $fieldsPages, $cmsFile, $cmsFilename);

            // Check both uploads succeeded
            if ($responseCatalog->getStatusCode() === 204 && $responsePages->getStatusCode() === 204) {
                // Move files to sent folder
                rename($catalogFile, PathHelper::getSentDir() . $catalogFilename);
                rename($cmsFile, PathHelper::getSentDir() . $cmsFilename);

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
}
