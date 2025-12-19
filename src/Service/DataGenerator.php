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

namespace Dialog\AskDialog\Service;

use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\ProductRepository;
use Dialog\AskDialog\Repository\CombinationRepository;
use Dialog\AskDialog\Repository\ImageRepository;
use Dialog\AskDialog\Repository\StockRepository;
use Dialog\AskDialog\Repository\CategoryRepository;
use Dialog\AskDialog\Repository\TagRepository;
use Dialog\AskDialog\Repository\FeatureRepository;
use Dialog\AskDialog\Repository\LanguageRepository;

class DataGenerator{
    private $products = [];

    // Repositories
    private $productRepository;
    private $combinationRepository;
    private $imageRepository;
    private $stockRepository;
    private $categoryRepository;
    private $tagRepository;
    private $featureRepository;
    private $languageRepository;

    // Preloaded data (indexed for O(1) lookup)
    private $productsData = [];
    private $combinationsData = [];
    private $combinationAttributesData = [];
    private $productImagesData = [];
    private $combinationImagesData = [];
    private $productStockData = [];
    private $combinationStockData = [];
    private $productCategoriesData = [];
    private $categoriesData = [];
    private $productTagsData = [];
    private $productFeaturesData = [];

    public function __construct()
    {
        $this->productRepository = new ProductRepository();
        $this->combinationRepository = new CombinationRepository();
        $this->imageRepository = new ImageRepository();
        $this->stockRepository = new StockRepository();
        $this->categoryRepository = new CategoryRepository();
        $this->tagRepository = new TagRepository();
        $this->featureRepository = new FeatureRepository();
        $this->languageRepository = new LanguageRepository();
    }

    /**
     * Generates CMS pages data and saves to JSON file
     *
     * @param int $idLang Language ID (default: shop default language)
     * @return string Path to generated JSON file
     */
    public function generateCMSData($idLang = null)
    {
        if ($idLang === null) {
            $idLang = (int)\Configuration::get('PS_LANG_DEFAULT');
        }

        // Retrieve all CMS pages
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'cms_lang WHERE id_lang = ' . (int)$idLang;
        $cmsPages = \Db::getInstance()->executeS($sql);

        $cmsData = [];
        foreach ($cmsPages as $page) {
            $cmsData[] = [
                'title' => $page['meta_title'],
                'content' => $page['content']
            ];
        }

        // Generate filename with timestamp and unique hash
        $timestamp = date('Ymd_His');
        $hash = substr(md5($timestamp . rand()), 0, 8);
        $filename = 'cms_' . $timestamp . '_' . $hash . '.json';
        $tempFile = PathHelper::getTmpDir() . $filename;

        file_put_contents($tempFile, json_encode($cmsData));

        return $tempFile;
    }

    /**
     * Bulk load all data for multiple products
     * This method loads ALL data in ~13 queries instead of N*24 queries
     *
     * @param array $productIds Array of product IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     * @return void
     */
    private function bulkLoadData(array $productIds, $idLang, $idShop)
    {
        if (empty($productIds)) {
            return;
        }

        // 1. Load products with multilingual data (1 query)
        $this->productsData = $this->productRepository->findByIdsWithLang($productIds, $idLang, $idShop);

        // 2. Load combinations (1 query)
        $this->combinationsData = $this->combinationRepository->findByProductIds($productIds);

        // Get all combination IDs for next queries
        $combinationIds = $this->combinationRepository->getCombinationIdsByProductIds($productIds);

        if (!empty($combinationIds)) {
            // 3. Load combination attributes (1 query)
            $this->combinationAttributesData = $this->combinationRepository->findAttributesByCombinationIds($combinationIds, $idLang);

            // 4. Load combination images (1 query)
            $this->combinationImagesData = $this->imageRepository->findByCombinationIds($combinationIds);

            // 5. Load combination stock (1 query)
            $this->combinationStockData = $this->stockRepository->findByCombinationIds($combinationIds, $idShop);
        }

        // 6. Load product images (1 query)
        $this->productImagesData = $this->imageRepository->findByProductIds($productIds, $idShop);

        // 7. Load product stock (1 query)
        $this->productStockData = $this->stockRepository->findByProductIds($productIds, $idShop);

        // 8. Load product-category relations (1 query)
        $this->productCategoriesData = $this->categoryRepository->findCategoryIdsByProductIds($productIds);

        // Get all unique category IDs and load category details
        $categoryIds = [];
        foreach ($this->productCategoriesData as $categories) {
            foreach ($categories as $cat) {
                $categoryIds[] = $cat['id_category'];
            }
        }
        $categoryIds = array_unique($categoryIds);

        if (!empty($categoryIds)) {
            // 9. Load categories (1 query)
            $this->categoriesData = $this->categoryRepository->findByIds($categoryIds, $idLang, $idShop);
        }

        // 10. Load tags (1 query)
        $this->productTagsData = $this->tagRepository->findByProductIds($productIds, $idLang);

        // 11. Load features (1 query)
        $this->productFeaturesData = $this->featureRepository->findByProductIds($productIds, $idLang);
    }

    /**
     * Generates catalog data and saves to JSON file
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @param string $countryCode Country ISO code for tax calculation
     * @return string Path to generated JSON file
     * @throws \Exception If no products found or data generation fails
     */
    public function generateCatalogData($idShop, $idLang, $countryCode = 'fr')
    {
        // Get all product IDs for current shop
        $productIds = $this->getProductIdsForShop($idShop);

        if (empty($productIds)) {
            throw new \Exception('No products found for shop ID ' . $idShop);
        }

        // Bulk load ALL data upfront (11 queries total instead of N*24)
        $this->bulkLoadData($productIds, $idLang, $idShop);

        // Generate catalog data (no more queries, uses preloaded data)
        $catalogData = [];
        $linkObj = new \Link();

        foreach ($productIds as $productId) {
            $productData = $this->getProductData($productId, $idLang, $linkObj, $countryCode);
            if (!empty($productData)) {
                $catalogData[] = $productData;
            }
        }

        if (empty($catalogData)) {
            throw new \Exception('No valid product data generated');
        }

        // Generate filename with timestamp and unique hash
        $timestamp = date('Ymd_His');
        $hash = substr(md5($timestamp . rand()), 0, 8);
        $filename = 'catalog_' . $timestamp . '_' . $hash . '.json';
        $tempFile = PathHelper::getTmpDir() . $filename;

        file_put_contents($tempFile, json_encode($catalogData));

        return $tempFile;
    }

    /**
     * Get all product IDs for a specific shop
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

        $results = \Db::getInstance()->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'id_product');
    }

    public function getProductData($product_id, $defaultLang, $linkObj, $countryCode = 'fr') {
        // Use preloaded data instead of loading Product object
        if (!isset($this->productsData[$product_id])) {
            return [];
        }

        $productData = $this->productsData[$product_id];

        $productItem = [];
        $publishedAt = (new \DateTime($productData['date_add']))->format('Y-m-d\TH:i:s\Z');
        $productItem["publishedAt"] = $publishedAt;
        $productItem["modifiedDescription"] = $productData['description_short'];
        $productItem["description"] = $productData['description'];
        $productItem["title"] = $productData['name'];
        $productItem["handle"] = $productData['link_rewrite'];


        // Handle product price with tax for the country
        $taxCalculator = null;
        if($countryCode != null){
            $addressObj = new \Address();
            $countryObj = new \Country();
            $idCountry = $countryObj::getByIso($countryCode);
            $addressObj->id_state = 0;
            $addressObj->postcode = '';
            $addressObj->id_city = 0;
            $addressObj->id_manufacturer = 0;
            $addressObj->id_customer = 0;
            $addressObj->id = 0;
            $addressObj->id_address = 0;
            $addressObj->id_country = $idCountry;
            $type = 'country';
            $taxManager = \TaxManagerFactory::getManager($addressObj, $type);
            $taxCalculator = $taxManager->getTaxCalculator();
            $productItem["price"] = $taxCalculator->addTaxes(\Product::getPriceStatic($product_id, true, null, 2, null, false, true));
        }else{
            $productItem["price"] = \Product::getPriceStatic($product_id, true, null, 2, null, false, true);
        }

        // Use preloaded combinations data
        $productCombinations = isset($this->combinationsData[$product_id]) ? $this->combinationsData[$product_id] : [];
        $productItem['totalVariants'] = count($productCombinations);

        $variants = [];
        foreach($productCombinations as $combination) {
            $combinationId = (int)$combination['id_product_attribute'];
            $variant = [];

            // Use preloaded combination images
            if (isset($this->combinationImagesData[$combinationId]) && !empty($this->combinationImagesData[$combinationId])) {
                $firstImage = $this->combinationImagesData[$combinationId][0];
                $variant['image'] = [
                    "url" => $linkObj->getImageLink($productData['link_rewrite'], $firstImage['id_image'])
                ];
            }

            $variant["metafields"] = [];

            // Build display name from attributes with group names
            $displayNameParts = [];
            if (isset($this->combinationAttributesData[$combinationId])) {
                foreach ($this->combinationAttributesData[$combinationId] as $attr) {
                    $displayNameParts[] = $attr['group_name'] . ' - ' . $attr['attribute_name'];
                }
            }

            if (!empty($displayNameParts)) {
                $variant["displayName"] = $productData['name'] . ' : ' . implode(', ', $displayNameParts);
            } else {
                $variant["displayName"] = $productData['name'];
            }
            $variant["title"] = $variant["displayName"];

            // Use preloaded stock data
            $stock = isset($this->combinationStockData[$combinationId]) ? $this->combinationStockData[$combinationId] : null;
            $variant["inventoryQuantity"] = $stock ? (int)$stock['quantity'] : 0;

            // Calculate price with tax if needed
            if($taxCalculator != null){
                $variant["price"] = $taxCalculator->addTaxes(\Product::getPriceStatic($product_id, true, $combinationId, 2, null, false, true));
            }else{
                $variant["price"] = \Product::getPriceStatic($product_id, false, $combinationId, 2, null, false, true);
            }

            // Use preloaded attributes for selectedOptions
            $options = [];
            if (isset($this->combinationAttributesData[$combinationId])) {
                foreach ($this->combinationAttributesData[$combinationId] as $attr) {
                    $options[] = [
                        'name' => $attr['group_name'],
                        'value' => $attr['attribute_name'],
                    ];
                }
            }
            $variant["selectedOptions"] = $options;
            $variant["id"] = $combinationId;
            $variants[] = $variant;
        }

        $productItem['variants'] = $variants;

        // Use preloaded product images
        $images = [];
        $productImages = isset($this->productImagesData[$product_id]) ? $this->productImagesData[$product_id] : [];

        foreach ($productImages as $image) {
            $linkImage = $linkObj->getImageLink($productData['link_rewrite'], $image['id_image'], 'large_default');

            if($image['cover'] != null && $image['cover']=='1'){
                $productItem['featuredImage'] = ['url'=>$linkImage];
            }
            $images[] = ['url'=>$linkImage];
        }
        $productItem["images"] = $images;

        // Use preloaded product stock
        $stock = isset($this->productStockData[$product_id]) ? $this->productStockData[$product_id] : null;
        $productItem["totalInventory"] = $stock ? (int)$stock['quantity'] : 0;
        $productItem["status"] = $productData['active'] ? "ACTIVE" : "NOT ACTIVE";

        // Use preloaded categories
        $categoryItems = [];
        if (isset($this->productCategoriesData[$product_id])) {
            foreach ($this->productCategoriesData[$product_id] as $catRelation) {
                $categoryId = $catRelation['id_category'];
                if (isset($this->categoriesData[$categoryId])) {
                    $category = $this->categoriesData[$categoryId];
                    $categoryItems[] = [
                        "description" => $category['description'],
                        "title" => $category['name']
                    ];
                }
            }
        }
        $productItem["categories"] = $categoryItems;

        // Use preloaded tags
        $productItem["tags"] = [];
        if (isset($this->productTagsData[$product_id])) {
            foreach ($this->productTagsData[$product_id] as $tag) {
                $productItem["tags"][] = $tag['name'];
            }
        }

        // Use preloaded features
        $productItem["metafields"] = [];
        if (isset($this->productFeaturesData[$product_id])) {
            foreach ($this->productFeaturesData[$product_id] as $feature) {
                $productItem["metafields"][] = [
                    "name" => $feature['feature_name'],
                    "value" => $feature['feature_value'] !== null ? $feature['feature_value'] : ""
                ];
            }
        }

        if($productItem['totalVariants']>0){
            $productItem["hasOnlyDefaultVariant"] = 0;
        }else{
            $productItem["hasOnlyDefaultVariant"] = 1;
        }
        $productItem["id"] = (int)$product_id;
        return $productItem;
    }

    /**
     * Get catalog data for all products in the shop
     *
     * @return array Array of product data formatted for Dialog AI
     */
    public function getCatalogData(){
        $defaultLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $idShop = (int)\Context::getContext()->shop->id;

        // Get all product IDs for the shop using Repository
        $productIds = $this->productRepository->getProductIdsByShop($idShop);

        if (empty($productIds)) {
            return [];
        }

        // Bulk load all data upfront (crucial for performance and data availability)
        $this->bulkLoadData($productIds, $defaultLang, $idShop);

        $linkObj = new \Link();
        foreach($productIds as $productId){
            if (!empty($productData = $this->getProductData($productId, $defaultLang, $linkObj))) {
                $this->products[] = $productData;
			}
        }
        return $this->products;
    }

    /**
     * Get language data for all languages
     *
     * @return array Array of language data formatted for Dialog AI
     */
    public function getLanguageData(){
        $languages = $this->languageRepository->findAll();
        $languagesData = [];
        foreach($languages as $language){
            $languagesData[] = [
                "id" => (int)$language['id_lang'],
                "name" => $language['name'],
                "isoCode" => $language['iso_code'],
                "default" => (int)$language['active']
            ];
        }
        return $languagesData;
    }
}
