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

use Dialog\AskDialog\Form\GeneralDataConfiguration;
use Dialog\AskDialog\Helper\Logger;
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
     * Clear all preloaded data to free memory
     * Must be called after processing each batch
     *
     * @return void
     */
    private function clearLoadedData()
    {
        $this->productsData = [];
        $this->combinationsData = [];
        $this->combinationAttributesData = [];
        $this->productImagesData = [];
        $this->combinationImagesData = [];
        $this->productStockData = [];
        $this->combinationStockData = [];
        $this->productCategoriesData = [];
        $this->categoriesData = [];
        $this->productTagsData = [];
        $this->productFeaturesData = [];

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Get total product count for a shop
     *
     * @param int $idShop Shop ID
     *
     * @return int Total number of products
     */
    public function getProductCount($idShop)
    {
        $productIds = $this->productRepository->getProductIdsByShop($idShop);

        return count($productIds);
    }

    /**
     * Generates catalog data using batch processing and streaming to file
     * Memory-efficient: processes products in batches and writes directly to file
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @param string $countryCode Country code for tax calculation
     * @param int $batchSize Number of products per batch (default: from configuration)
     * @param callable|null $progressCallback Callback called after each batch: function(int $batchCompleted, int $totalBatches)
     *
     * @return string Path to generated JSON file
     *
     * @throws \Exception If no products found or file write fails
     */
    public function generateFileBatched($idShop, $idLang, $countryCode = 'fr', $batchSize = null, $progressCallback = null)
    {
        if ($batchSize === null) {
            $configBatchSize = \Configuration::get(GeneralDataConfiguration::ASKDIALOG_BATCH_SIZE);
            $batchSize = $configBatchSize !== false ? (int) $configBatchSize : GeneralDataConfiguration::DEFAULT_BATCH_SIZE;
        }
        $startTime = microtime(true);
        Logger::log('[AskDialog] ProductExport::generateFileBatched: START (batchSize=' . $batchSize . ')', 1);

        // Get all product IDs for current shop
        $productIds = $this->productRepository->getProductIdsByShop($idShop);
        $totalProducts = count($productIds);
        Logger::log('[AskDialog] ProductExport::generateFileBatched: Found ' . $totalProducts . ' products', 1);

        if (empty($productIds)) {
            Logger::log('[AskDialog] ProductExport::generateFileBatched: ERROR - No products found for shop ID ' . $idShop, 3);
            throw new \Exception('No products found for shop ID ' . $idShop);
        }

        // Split into batches
        $batches = array_chunk($productIds, $batchSize);
        $totalBatches = count($batches);
        Logger::log('[AskDialog] ProductExport::generateFileBatched: Split into ' . $totalBatches . ' batches', 1);

        // Generate unique file path and open for streaming write
        $tmpFile = PathHelper::generateTmpFilePath('catalog');
        $handle = fopen($tmpFile, 'w');

        if ($handle === false) {
            Logger::log('[AskDialog] ProductExport::generateFileBatched: ERROR - Failed to open file ' . $tmpFile, 3);
            throw new \Exception('Failed to open catalog file for writing. Check permissions on var/modules/askdialog/');
        }

        // Start JSON array
        fwrite($handle, '[');

        $firstProduct = true;
        $processedCount = 0;

        foreach ($batches as $batchIndex => $batchProductIds) {
            $batchNumber = $batchIndex + 1;
            Logger::log('[AskDialog] ProductExport::generateFileBatched: Processing batch ' . $batchNumber . '/' . $totalBatches, 1);

            // Load data for this batch only
            $this->bulkLoadData($batchProductIds, $idLang, $idShop);

            // Process and write each product
            $linkObj = new \Link();
            foreach ($batchProductIds as $productId) {
                $productData = $this->getProductData($productId, $idLang, $linkObj, $countryCode);
                if (!empty($productData)) {
                    if (!$firstProduct) {
                        fwrite($handle, ',');
                    }
                    fwrite($handle, json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $firstProduct = false;
                    $processedCount++;
                }
            }

            // Free memory after each batch
            $this->clearLoadedData();
            unset($linkObj);

            Logger::log('[AskDialog] ProductExport::generateFileBatched: Batch ' . $batchNumber . ' completed, ' . $processedCount . ' products processed', 1);

            // Progress callback
            if ($progressCallback !== null) {
                $progressCallback($batchNumber, $totalBatches);
            }
        }

        // End JSON array
        fwrite($handle, ']');
        fclose($handle);

        $duration = round(microtime(true) - $startTime, 2);
        $fileSizeMb = round(filesize($tmpFile) / 1024 / 1024, 2);
        Logger::log('[AskDialog] ProductExport::generateFileBatched: SUCCESS - ' . $fileSizeMb . 'MB, ' . $processedCount . ' products in ' . $duration . 's', 1);

        return $tmpFile;
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
        $startTime = microtime(true);
        Logger::log('[AskDialog] ProductExport::generateFile: START', 1);

        // Get all product IDs for current shop using Repository
        $productIds = $this->productRepository->getProductIdsByShop($idShop);
        Logger::log('[AskDialog] ProductExport::generateFile: Found ' . count($productIds) . ' product IDs', 1);

        if (empty($productIds)) {
            Logger::log('[AskDialog] ProductExport::generateFile: ERROR - No products found for shop ID ' . $idShop, 3);
            throw new \Exception('No products found for shop ID ' . $idShop);
        }

        Logger::log('[AskDialog] ProductExport::generateFile: Starting bulkLoadData...', 1);
        $this->bulkLoadData($productIds, $idLang, $idShop);
        Logger::log('[AskDialog] ProductExport::generateFile: bulkLoadData completed', 1);

        // Generate catalog data, uses preloaded data
        $catalogData = [];
        $linkObj = new \Link();

        Logger::log('[AskDialog] ProductExport::generateFile: Starting product transformation...', 1);
        foreach ($productIds as $productId) {
            $productData = $this->getProductData($productId, $idLang, $linkObj, $countryCode);
            if (!empty($productData)) {
                $catalogData[] = $productData;
            }
        }
        Logger::log('[AskDialog] ProductExport::generateFile: Transformed ' . count($catalogData) . '/' . count($productIds) . ' products', 1);

        if (empty($catalogData)) {
            Logger::log('[AskDialog] ProductExport::generateFile: ERROR - No valid product data generated', 3);
            throw new \Exception('No valid product data generated');
        }

        // Generate unique file path
        $tmpFile = PathHelper::generateTmpFilePath('catalog');
        Logger::log('[AskDialog] ProductExport::generateFile: Writing to file ' . $tmpFile, 1);

        // JSON optimized for LLM: unescaped unicode/slashes, pretty print for readability
        $jsonData = json_encode($catalogData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if ($jsonData === false) {
            $jsonError = json_last_error_msg();
            Logger::log('[AskDialog] ProductExport::generateFile: ERROR - JSON encoding failed: ' . $jsonError, 3);
            throw new \Exception('JSON encoding failed: ' . $jsonError);
        }

        $bytesWritten = file_put_contents($tmpFile, $jsonData);

        if ($bytesWritten === false) {
            Logger::log('[AskDialog] ProductExport::generateFile: ERROR - Failed to write file ' . $tmpFile . ' - check permissions on var/modules/', 3);
            throw new \Exception('Failed to write catalog file. Check permissions on var/modules/askdialog/');
        }

        $duration = round(microtime(true) - $startTime, 2);
        $fileSizeMb = round($bytesWritten / 1024 / 1024, 2);
        Logger::log('[AskDialog] ProductExport::generateFile: SUCCESS - ' . $fileSizeMb . 'MB written in ' . $duration . 's', 1);

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
            Logger::log('[AskDialog] bulkLoadData: No product IDs provided', 2);

            return;
        }

        Logger::log('[AskDialog] bulkLoadData: START - ' . count($productIds) . ' products, idLang=' . $idLang . ', idShop=' . $idShop, 1);

        // 1. Load products with multilingual data
        $this->productsData = $this->productRepository->findByIdsWithLang($productIds, $idLang, $idShop);
        Logger::log('[AskDialog] bulkLoadData: [1/10] Products loaded: ' . count($this->productsData), 1);

        // 2. Load combinations
        $this->combinationsData = $this->combinationRepository->findByProductIds($productIds);
        $combinationsCount = array_sum(array_map('count', $this->combinationsData));
        Logger::log('[AskDialog] bulkLoadData: [2/10] Combinations loaded: ' . $combinationsCount, 1);

        // Get all combination IDs for next queries
        $combinationIds = $this->combinationRepository->getCombinationIdsByProductIds($productIds);
        Logger::log('[AskDialog] bulkLoadData: Combination IDs: ' . count($combinationIds), 1);

        if (!empty($combinationIds)) {
            // 3. Load combination attributes
            $this->combinationAttributesData = $this->combinationRepository->findAttributesByCombinationIds($combinationIds, $idLang);
            Logger::log('[AskDialog] bulkLoadData: [3/10] Combination attributes loaded: ' . count($this->combinationAttributesData), 1);

            // 4. Load combination images
            $this->combinationImagesData = $this->imageRepository->findByCombinationIds($combinationIds);
            Logger::log('[AskDialog] bulkLoadData: [4/10] Combination images loaded: ' . count($this->combinationImagesData), 1);

            // 5. Load combination stock
            $this->combinationStockData = $this->stockRepository->findByCombinationIds($combinationIds, $idShop);
            Logger::log('[AskDialog] bulkLoadData: [5/10] Combination stock loaded: ' . count($this->combinationStockData), 1);
        } else {
            Logger::log('[AskDialog] bulkLoadData: [3-5/10] Skipped (no combinations)', 1);
        }

        // 6. Load product images
        $this->productImagesData = $this->imageRepository->findByProductIds($productIds, $idShop);
        Logger::log('[AskDialog] bulkLoadData: [6/10] Product images loaded: ' . count($this->productImagesData), 1);

        // 7. Load product stock
        $this->productStockData = $this->stockRepository->findByProductIds($productIds, $idShop);
        Logger::log('[AskDialog] bulkLoadData: [7/10] Product stock loaded: ' . count($this->productStockData), 1);

        // 8. Load product-category relations
        $this->productCategoriesData = $this->categoryRepository->findCategoryIdsByProductIds($productIds);
        Logger::log('[AskDialog] bulkLoadData: [8/10] Product categories loaded: ' . count($this->productCategoriesData), 1);

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
            Logger::log('[AskDialog] bulkLoadData: Categories details loaded: ' . count($this->categoriesData), 1);
        }

        // 9. Load product tags
        $this->productTagsData = $this->tagRepository->findByProductIds($productIds, $idLang);
        Logger::log('[AskDialog] bulkLoadData: [9/10] Product tags loaded: ' . count($this->productTagsData), 1);

        // 10. Load product features
        $this->productFeaturesData = $this->featureRepository->findByProductIds($productIds, $idLang);
        Logger::log('[AskDialog] bulkLoadData: [10/10] Product features loaded: ' . count($this->productFeaturesData), 1);

        Logger::log('[AskDialog] bulkLoadData: COMPLETED', 1);
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

    /**
     * Get all product IDs for a shop
     *
     * @param int $idShop Shop ID
     *
     * @return array Array of product IDs
     */
    public function getProductIds($idShop)
    {
        return $this->productRepository->getProductIdsByShop($idShop);
    }

    /**
     * Process products in a resumable way with time limit protection
     * Uses NDJSON (newline-delimited JSON) as intermediate format for safe appending
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @param string $countryCode Country code for tax calculation
     * @param int $offset Number of products already exported (resume point)
     * @param int $batchSize Number of products per batch
     * @param int $timeLimit Max seconds to run (should be max_execution_time - safety margin)
     * @param string|null $tmpFilePath Existing NDJSON temp file to append to, or null to create new
     *
     * @return array{
     *     productsProcessed: int,
     *     isComplete: bool,
     *     tmpFilePath: string,
     *     totalProducts: int
     * }
     *
     * @throws \Exception If file operations fail
     */
    public function processResumableBatch($idShop, $idLang, $countryCode, $offset, $batchSize, $timeLimit, $tmpFilePath = null)
    {
        $startTime = time();
        Logger::log('[AskDialog] ProductExport::processResumableBatch: START offset=' . $offset . ', batchSize=' . $batchSize . ', timeLimit=' . $timeLimit . 's', 1);

        // Get all product IDs
        $productIds = $this->productRepository->getProductIdsByShop($idShop);
        $totalProducts = count($productIds);

        if (empty($productIds)) {
            Logger::log('[AskDialog] ProductExport::processResumableBatch: No products found', 3);
            throw new \Exception('No products found for shop ID ' . $idShop);
        }

        // Check if already complete
        if ($offset >= $totalProducts) {
            Logger::log('[AskDialog] ProductExport::processResumableBatch: Already complete (offset >= total)', 1);

            return [
                'productsProcessed' => 0,
                'isComplete' => true,
                'tmpFilePath' => $tmpFilePath,
                'totalProducts' => $totalProducts,
            ];
        }

        // Create new file if needed (NDJSON format: .ndjson extension)
        if ($tmpFilePath === null) {
            $tmpFilePath = PathHelper::generateTmpFilePath('catalog', 'ndjson');
        }

        // Get products to process (from offset)
        $remainingProductIds = array_slice($productIds, $offset);
        $batches = array_chunk($remainingProductIds, $batchSize);

        $processedCount = 0;
        $linkObj = new \Link();

        foreach ($batches as $batchProductIds) {
            // Check time limit before processing batch
            $elapsed = time() - $startTime;
            if ($elapsed >= $timeLimit) {
                Logger::log('[AskDialog] ProductExport::processResumableBatch: Time limit reached after ' . $elapsed . 's', 1);
                break;
            }

            // Load and process batch
            $this->bulkLoadData($batchProductIds, $idLang, $idShop);

            // Build NDJSON content for this batch
            $ndjsonLines = '';
            foreach ($batchProductIds as $productId) {
                $productData = $this->getProductData($productId, $idLang, $linkObj, $countryCode);
                if (!empty($productData)) {
                    $ndjsonLines .= json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    $processedCount++;
                }
            }

            // Append to file (atomic write for this batch)
            if (!empty($ndjsonLines)) {
                file_put_contents($tmpFilePath, $ndjsonLines, FILE_APPEND | LOCK_EX);
            }

            // Free memory
            $this->clearLoadedData();

            Logger::log('[AskDialog] ProductExport::processResumableBatch: Batch done, processed=' . $processedCount . ', elapsed=' . (time() - $startTime) . 's', 1);
        }

        // Check if complete
        $newOffset = $offset + $processedCount;
        $isComplete = ($newOffset >= $totalProducts);

        Logger::log('[AskDialog] ProductExport::processResumableBatch: END processed=' . $processedCount . ', newOffset=' . $newOffset . '/' . $totalProducts . ', isComplete=' . ($isComplete ? 'true' : 'false'), 1);

        return [
            'productsProcessed' => $processedCount,
            'isComplete' => $isComplete,
            'tmpFilePath' => $tmpFilePath,
            'totalProducts' => $totalProducts,
        ];
    }

    /**
     * Convert NDJSON file to final JSON array file
     * Called once export is complete
     *
     * @param string $ndjsonFilePath Path to NDJSON file
     *
     * @return string Path to final JSON file
     *
     * @throws \Exception If conversion fails
     */
    public function convertNdjsonToJson($ndjsonFilePath)
    {
        Logger::log('[AskDialog] ProductExport::convertNdjsonToJson: START', 1);

        if (!file_exists($ndjsonFilePath)) {
            throw new \Exception('NDJSON file not found: ' . $ndjsonFilePath);
        }

        // Read NDJSON file line by line
        $products = [];
        $handle = fopen($ndjsonFilePath, 'r');
        if ($handle === false) {
            throw new \Exception('Failed to open NDJSON file');
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (!empty($line)) {
                $product = json_decode($line, true);
                if ($product !== null) {
                    $products[] = $product;
                }
            }
        }
        fclose($handle);

        Logger::log('[AskDialog] ProductExport::convertNdjsonToJson: Read ' . count($products) . ' products', 1);

        // Generate final JSON file
        $finalFilePath = PathHelper::generateTmpFilePath('catalog', 'json');
        $jsonContent = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonContent === false) {
            throw new \Exception('JSON encoding failed: ' . json_last_error_msg());
        }

        $bytesWritten = file_put_contents($finalFilePath, $jsonContent);
        if ($bytesWritten === false) {
            throw new \Exception('Failed to write final JSON file');
        }

        // Delete NDJSON temp file
        unlink($ndjsonFilePath);

        $fileSizeMb = round($bytesWritten / 1024 / 1024, 2);
        Logger::log('[AskDialog] ProductExport::convertNdjsonToJson: SUCCESS - ' . $fileSizeMb . 'MB', 1);

        return $finalFilePath;
    }
}
