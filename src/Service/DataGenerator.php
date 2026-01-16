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

use Dialog\AskDialog\Repository\LanguageRepository;
use Dialog\AskDialog\Service\Export\CategoryExportService;
use Dialog\AskDialog\Service\Export\CmsExportService;
use Dialog\AskDialog\Service\Export\ProductExportService;

/**
 * Data Generator Service - Orchestrator for export services
 *
 * This class acts as a facade, delegating export operations to specialized services.
 * Maintains backward compatibility with existing controllers.
 */
class DataGenerator
{
    private $productExport;
    private $cmsExport;
    private $categoryExport;
    private $languageRepository;

    public function __construct()
    {
        $this->productExport = new ProductExportService();
        $this->cmsExport = new CmsExportService();
        $this->categoryExport = new CategoryExportService();
        $this->languageRepository = new LanguageRepository();
    }

    /**
     * Generates CMS pages data and saves to JSON file
     *
     * @param int|null $idLang Language ID (default: shop default language)
     *
     * @return string Path to generated JSON file
     */
    public function generateCMSData($idLang = null)
    {
        return $this->cmsExport->generateFile($idLang);
    }

    /**
     * Generates category data and saves to JSON file
     *
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     *
     * @return string Path to generated JSON file
     */
    public function generateCategoryData($idLang, $idShop)
    {
        return $this->categoryExport->generateFile($idLang, $idShop);
    }

    /**
     * Generates product catalog data and saves to JSON file
     *
     * Products contain their categories directly
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @param string $countryCode Country code for tax calculation
     *
     * @return string Path to generated JSON file
     *
     * @throws \Exception If no products found or no valid data generated
     */
    public function generateCatalogData($idShop, $idLang, $countryCode = 'fr')
    {
        return $this->productExport->generateFile($idShop, $idLang, $countryCode);
    }

    /**
     * Returns category data for API consumption (no file generation)
     *
     * @return array Category tree structure
     */
    public function getCategoryData()
    {
        $idShop = (int) \Context::getContext()->shop->id;
        $idLang = (int) \Context::getContext()->language->id;

        return $this->categoryExport->getData($idShop, $idLang);
    }

    /**
     * Get catalog data for all products in the shop (for API consumption)
     *
     * @return array Array of product data formatted for Dialog AI
     */
    public function getCatalogData()
    {
        return $this->productExport->getData();
    }

    /**
     * Get single product data for API
     *
     * @param int $product_id Product ID
     * @param int $defaultLang Language ID
     * @param \Link $linkObj Link object for URL generation
     * @param string $countryCode Country code for tax calculation
     *
     * @return array Product data formatted for Dialog AI
     */
    public function getProductData($product_id, $defaultLang, $linkObj, $countryCode = 'fr')
    {
        return $this->productExport->getSingleProductData($product_id, $defaultLang, $linkObj, $countryCode);
    }

    /**
     * Get language data for all languages
     *
     * @return array Array of language data formatted for Dialog AI
     */
    public function getLanguageData()
    {
        $languages = $this->languageRepository->findAll();

        $languageData = [];
        foreach ($languages as $language) {
            $languageData[] = [
                'id' => (int) $language['id_lang'],
                'name' => $language['name'],
                'iso_code' => $language['iso_code'],
                'locale' => $language['locale'],
                'active' => (bool) $language['active'],
            ];
        }

        return $languageData;
    }
}
