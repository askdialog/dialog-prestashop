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

/**
 * Class AskDialogApiModuleFrontController
 * 
 * Public API endpoints for Dialog AI platform
 * Provides product catalog and language data
 */
class AskDialogApiModuleFrontController extends ModuleFrontController
{
    /**
     * Initialize controller and verify public API key authentication
     */
    public function initContent()
    {
        parent::initContent();
        
        $headers = getallheaders();
        
        // Check Authorization header format
        if (!isset($headers['Authorization']) || substr($headers['Authorization'], 0, 6) !== 'Token ') {
            $this->sendJsonResponse(['error' => 'Public API Token is missing'], 401);
        }
        
        // Validate public API key
        $expectedToken = 'Token ' . Configuration::get('ASKDIALOG_API_KEY_PUBLIC');
        if ($headers['Authorization'] !== $expectedToken) {
            $this->sendJsonResponse(['error' => 'Public API Token is wrong'], 403);
        }
        
        $this->ajax = true;
    }

    /**
     * Main AJAX handler for API actions
     */
    public function displayAjax()
    {
        $action = Tools::getValue('action');
        $dataGenerator = new DataGenerator();

        switch ($action) {
            case 'getCatalogData':
                $this->handleGetCatalogData($dataGenerator);
                break;
                
            case 'getLanguageData':
                $this->handleGetLanguageData($dataGenerator);
                break;
                
            case 'getProductData':
                $this->handleGetProductData($dataGenerator);
                break;
                
            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action'
                ], 400);
        }
    }

    /**
     * Handles getCatalogData action
     * Returns full product catalog
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleGetCatalogData($dataGenerator)
    {
        $catalogData = $dataGenerator->getCatalogData();
        $this->sendJsonResponse($catalogData);
    }

    /**
     * Handles getLanguageData action
     * Returns available languages
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleGetLanguageData($dataGenerator)
    {
        $languageData = $dataGenerator->getLanguageData();
        $this->sendJsonResponse($languageData);
    }

    /**
     * Handles getProductData action
     * Returns single product data with language and country context
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleGetProductData($dataGenerator)
    {
        $productId = (int)Tools::getValue('id');
        $countryCode = Tools::getValue('country_code', 'fr');
        $locale = Tools::getValue('locale');
        
        // Determine language ID
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');
        
        if (empty($countryCode) || empty($locale)) {
            $idLang = $defaultLang;
        } else {
            $idLang = Language::getIdByLocale($countryCode . '-' . $locale);
            
            if (!$idLang) {
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid country code or locale'
                ], 400);
            }
        }
        
        // Get product data
        $linkObj = new Link();
        $productData = $dataGenerator->getProductData($productId, $idLang, $linkObj, $countryCode);
        
        $this->sendJsonResponse($productData);
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
