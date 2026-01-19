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

use Dialog\AskDialog\Service\DataGenerator;
use Dialog\AskDialog\Traits\JsonResponseTrait;

/**
 * Class AskDialogApiModuleFrontController
 *
 * Public API endpoints for Dialog AI platform
 * Provides product catalog and language data
 */
class AskDialogApiModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    /**
     * Initialize controller and verify public API key authentication
     */
    public function initContent()
    {
        parent::initContent();

        $headers = getallheaders();
        $authHeader = $this->getHeaderCaseInsensitive($headers, 'Authorization');

        // Check Authorization header format
        if ($authHeader === null || substr($authHeader, 0, 6) !== 'Token ') {
            $this->sendJsonResponse(['error' => 'Public API Token is missing'], 401);
        }

        // Validate public API key
        $expectedToken = 'Token ' . Configuration::get('ASKDIALOG_API_KEY_PUBLIC');
        if ($authHeader !== $expectedToken) {
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

            case 'getCategoryData':
                $this->handleGetCategoryData($dataGenerator);
                break;

            default:
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid action',
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
     * Uses current context (language/country) by default
     * Can be overridden with country_code and locale parameters
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleGetProductData($dataGenerator)
    {
        // Accept both "id_product" (PrestaShop convention) and "id" (Dialog SDK)
        $productId = (int) Tools::getValue('id_product');
        if ($productId === 0) {
            $productId = (int) Tools::getValue('id');
        }

        // Use context language and country by default
        $idLang = (int) $this->context->language->id;
        $countryCode = $this->context->country->iso_code;

        // Allow override via parameters (for Dialog AI API calls)
        $paramLocale = Tools::getValue('locale');
        $paramCountryCode = Tools::getValue('country_code');

        if (!empty($paramCountryCode) && !empty($paramLocale)) {
            // Override with API parameters if provided
            $idLang = Language::getIdByLocale($paramCountryCode . '-' . $paramLocale);

            if (!$idLang) {
                $this->sendJsonResponse([
                    'status' => 'error',
                    'message' => 'Invalid country code or locale: ' . $paramCountryCode . '-' . $paramLocale,
                ], 400);
            }

            $countryCode = $paramCountryCode;
        }

        // Get product data
        $linkObj = new Link();
        $productData = $dataGenerator->getProductData($productId, $idLang, $linkObj, $countryCode);

        $this->sendJsonResponse($productData);
    }

    /**
     * Handles getCategoryData action
     * Returns category tree with multilingual data
     *
     * @param DataGenerator $dataGenerator
     */
    private function handleGetCategoryData($dataGenerator)
    {
        $categoryData = $dataGenerator->getCategoryData();
        $this->sendJsonResponse($categoryData);
    }
}
