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

namespace Dialog\AskDialog\Service\Export;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\CategoryRepository;
use Dialog\AskDialog\Repository\CombinationRepository;
use Dialog\AskDialog\Repository\FeatureRepository;
use Dialog\AskDialog\Repository\ImageRepository;
use Dialog\AskDialog\Repository\ProductRepository;
use Dialog\AskDialog\Repository\StockRepository;
use Dialog\AskDialog\Repository\TagRepository;

/**
 * Service responsible for product catalog export
 * Handles bulk loading, transformation, and file generation
 */
class ProductExportService
{
    // Repositories
    private $productRepository;
    private $combinationRepository;
    private $imageRepository;
    private $stockRepository;
    private $categoryRepository;
    private $tagRepository;
    private $featureRepository;

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
    }

    /**
     * Generates catalog data and saves to JSON file
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @param string $countryCode Country code for tax calculation
     *
     * @return string Path to generated JSON file
     *
     * @throws \Exception If no products found or no valid data generated
     */
    public function generateFile($idShop, $idLang, $countryCode = 'fr')
    {
        // Get all product IDs for current shop using Repository
        $productIds = $this->productRepository->getProductIdsByShop($idShop);

        if (empty($productIds)) {
            throw new \Exception('No products found for shop ID ' . $idShop);
        }

        $this->bulkLoadData($productIds, $idLang, $idShop);

        // Generate catalog data, uses preloaded data
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

        // Generate unique file path
        $tmpFile = PathHelper::generateTmpFilePath('catalog');

        // JSON optimized for LLM: unescaped unicode/slashes, pretty print for readability
        $jsonData = json_encode($catalogData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($tmpFile, $jsonData);

        return $tmpFile;
    }

    /**
     * Get catalog data for API consumption (no file generation)
     *
     * @return array Array of product data formatted for Dialog AI
     */
    public function getData()
    {
        $shopId = (int) \Context::getContext()->shop->id;
        $langId = (int) \Context::getContext()->language->id;
        $countryCode = \Context::getContext()->country->iso_code;

        $productIds = $this->productRepository->getProductIdsByShop($shopId);

        if (empty($productIds)) {
            return [];
        }

        $this->bulkLoadData($productIds, $langId, $shopId);

        $catalogData = [];
        $linkObj = new \Link();

        foreach ($productIds as $productId) {
            $productData = $this->getProductData($productId, $langId, $linkObj, $countryCode);
            if (!empty($productData)) {
                $catalogData[] = $productData;
            }
        }

        return $catalogData;
    }

    /**
     * Get single product data for API
     *
     * @param int $productId Product ID
     * @param int $idLang Language ID
     * @param \Link $linkObj Link object for URL generation
     * @param string $countryCode Country code for tax calculation
     *
     * @return array Product data
     */
    public function getSingleProductData($productId, $idLang, $linkObj, $countryCode = 'fr')
    {
        // For single product, load only this product's data
        $this->bulkLoadData([$productId], $idLang, (int) \Context::getContext()->shop->id);

        return $this->getProductData($productId, $idLang, $linkObj, $countryCode);
    }

    /**
     * Bulk load all data for multiple products
     *
     * @param array $productIds Array of product IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     *
     * @return void
     */
    private function bulkLoadData(array $productIds, $idLang, $idShop)
    {
        if (empty($productIds)) {
            return;
        }

        // 1. Load products with multilingual data
        $this->productsData = $this->productRepository->findByIdsWithLang($productIds, $idLang, $idShop);

        // 2. Load combinations
        $this->combinationsData = $this->combinationRepository->findByProductIds($productIds);

        // Get all combination IDs for next queries
        $combinationIds = $this->combinationRepository->getCombinationIdsByProductIds($productIds);

        if (!empty($combinationIds)) {
            // 3. Load combination attributes
            $this->combinationAttributesData = $this->combinationRepository->findAttributesByCombinationIds($combinationIds, $idLang);

            // 4. Load combination images
            $this->combinationImagesData = $this->imageRepository->findByCombinationIds($combinationIds);

            // 5. Load combination stock
            $this->combinationStockData = $this->stockRepository->findByCombinationIds($combinationIds, $idShop);
        }

        // 6. Load product images
        $this->productImagesData = $this->imageRepository->findByProductIds($productIds, $idShop);

        // 7. Load product stock
        $this->productStockData = $this->stockRepository->findByProductIds($productIds, $idShop);

        // 8. Load product-category relations
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
            $this->categoriesData = $this->categoryRepository->findByIds($categoryIds, $idLang, $idShop);
        }

        // 9. Load product tags
        $this->productTagsData = $this->tagRepository->findByProductIds($productIds, $idLang);

        // 10. Load product features
        $this->productFeaturesData = $this->featureRepository->findByProductIds($productIds, $idLang);
    }

    /**
     * Transform single product data into Dialog AI format
     *
     * @param int $product_id Product ID
     * @param int $defaultLang Language ID
     * @param \Link $linkObj Link object for URL generation
     * @param string $countryCode Country code for tax calculation
     *
     * @return array Product data formatted for Dialog AI
     */
    private function getProductData($product_id, $defaultLang, $linkObj, $countryCode = 'fr')
    {
        // Use preloaded data instead of loading Product object
        if (!isset($this->productsData[$product_id])) {
            return [];
        }

        $productData = $this->productsData[$product_id];

        $productItem = [];
        $publishedAt = (new \DateTime($productData['date_add']))->format('Y-m-d\TH:i:s\Z');
        $productItem['publishedAt'] = $publishedAt;
        $productItem['description'] = $productData['description'];
        $productItem['title'] = $productData['name'];
        $productItem['handle'] = $productData['link_rewrite'];

        // Handle product price with tax for the country
        if ($countryCode !== null) {
            $idCountry = \Country::getByIso($countryCode);
            $addressObj = new \Address();
            $addressObj->id_state = 0;
            $addressObj->postcode = '';
            $addressObj->id_manufacturer = 0;
            $addressObj->id_customer = 0;
            $addressObj->id = 0;
            $addressObj->id_country = $idCountry;

            $idTaxRulesGroup = \Product::getIdTaxRulesGroupByIdProduct($product_id);
            $taxManager = \TaxManagerFactory::getManager($addressObj, $idTaxRulesGroup);
            $taxCalculator = $taxManager->getTaxCalculator();
            $priceWithoutTax = \Product::getPriceStatic($product_id, false, null, 6, null, false, true);
            $productItem['price'] = round($taxCalculator->addTaxes($priceWithoutTax), 2);
        } else {
            $productItem['price'] = \Product::getPriceStatic($product_id, true, null, 2, null, false, true);
        }

        // Use preloaded combinations data
        $productCombinations = isset($this->combinationsData[$product_id]) ? $this->combinationsData[$product_id] : [];
        $productItem['totalVariants'] = count($productCombinations);

        $variants = [];
        foreach ($productCombinations as $combination) {
            $combinationId = (int) $combination['id_product_attribute'];
            $variant = [];

            // Use preloaded combination images
            if (isset($this->combinationImagesData[$combinationId]) && !empty($this->combinationImagesData[$combinationId])) {
                $firstImage = $this->combinationImagesData[$combinationId][0];
                $variant['image'] = [
                    'url' => $linkObj->getImageLink($productData['link_rewrite'], $firstImage['id_image']),
                ];
            }

            $variant['metafields'] = [];

            // Build display name from attributes with group names
            $displayNameParts = [];
            if (isset($this->combinationAttributesData[$combinationId])) {
                foreach ($this->combinationAttributesData[$combinationId] as $attr) {
                    $displayNameParts[] = $attr['group_name'] . ' - ' . $attr['attribute_name'];
                }
            }

            if (!empty($displayNameParts)) {
                $variant['displayName'] = $productData['name'] . ' : ' . implode(', ', $displayNameParts);
            } else {
                $variant['displayName'] = $productData['name'];
            }
            $variant['title'] = $variant['displayName'];

            // Use preloaded stock data
            $stock = isset($this->combinationStockData[$combinationId]) ? $this->combinationStockData[$combinationId] : null;
            $variant['inventoryQuantity'] = $stock ? (int) $stock['quantity'] : 0;

            // Calculate price with tax if needed
            if ($taxCalculator != null) {
                $variant['price'] = $taxCalculator->addTaxes(\Product::getPriceStatic($product_id, true, $combinationId, 2, null, false, true));
            } else {
                $variant['price'] = \Product::getPriceStatic($product_id, false, $combinationId, 2, null, false, true);
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
            $variant['selectedOptions'] = $options;
            $variant['id'] = $combinationId;
            $variants[] = $variant;
        }

        $productItem['variants'] = $variants;

        // Use preloaded product images
        $images = [];
        $productImages = isset($this->productImagesData[$product_id]) ? $this->productImagesData[$product_id] : [];

        foreach ($productImages as $image) {
            $linkImage = $linkObj->getImageLink($productData['link_rewrite'], $image['id_image'], 'large_default');

            if ($image['cover'] != null && $image['cover'] == '1') {
                $productItem['featuredImage'] = ['url' => $linkImage];
            }
            $images[] = ['url' => $linkImage];
        }
        $productItem['images'] = $images;

        // Use preloaded product stock
        $stock = isset($this->productStockData[$product_id]) ? $this->productStockData[$product_id] : null;
        $productItem['totalInventory'] = $stock ? (int) $stock['quantity'] : 0;
        $productItem['status'] = $productData['active'] ? 'ACTIVE' : 'NOT ACTIVE';

        // Use preloaded categories - build categories array with title and description
        $categories = [];
        if (isset($this->productCategoriesData[$product_id])) {
            foreach ($this->productCategoriesData[$product_id] as $catRelation) {
                $categoryId = $catRelation['id_category'];
                if (isset($this->categoriesData[$categoryId])) {
                    $category = $this->categoriesData[$categoryId];

                    // Concatenate description + additional_description (PS 8+)
                    $description = isset($category['description']) ? $category['description'] : null;
                    if (isset($category['additional_description']) && !empty($category['additional_description'])) {
                        $description = $description
                            ? $description . "\n\n" . $category['additional_description']
                            : $category['additional_description'];
                    }

                    $categories[] = [
                        'title' => $category['name'],
                        'description' => $description,
                    ];
                }
            }
        }
        $productItem['categories'] = $categories;

        // Use preloaded tags
        $productItem['tags'] = [];
        if (isset($this->productTagsData[$product_id])) {
            foreach ($this->productTagsData[$product_id] as $tag) {
                $productItem['tags'][] = $tag['name'];
            }
        }

        // Use preloaded features
        $productItem['metafields'] = [];
        if (isset($this->productFeaturesData[$product_id])) {
            foreach ($this->productFeaturesData[$product_id] as $feature) {
                $productItem['metafields'][] = [
                    'name' => $feature['feature_name'],
                    'value' => $feature['feature_value'] !== null ? $feature['feature_value'] : '',
                ];
            }
        }

        if ($productItem['totalVariants'] > 0) {
            $productItem['hasOnlyDefaultVariant'] = 0;
        } else {
            $productItem['hasOnlyDefaultVariant'] = 1;
        }
        $productItem['id'] = (int) $product_id;

        return $productItem;
    }
}
