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

class DataGenerator{
    private $products = [];

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

        // Generate catalog data
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
        $productObj = new \Product((int)$product_id);

        if (!\Validate::isLoadedObject($productObj)) {
			return [];
		}

        $productItem = [];
        $publishedAt = (new \DateTime($productObj->date_add))->format('Y-m-d\TH:i:s\Z');
        $productItem["publishedAt"] = $publishedAt;
        $productItem["modifiedDescription"] = $productObj->description_short[$defaultLang];
        $productItem["description"] = $productObj->description[$defaultLang];
        $productItem["title"] = $productObj->name[$defaultLang];
        $productItem["handle"] = $productObj->link_rewrite[$defaultLang];


        $taxCalculator = null;
        // Handle product price with tax for the country
        if($countryCode != null){
            $addressObj = new \Address();
            // Get Country ID from ISO country code
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
            $productItem["price"] = $taxCalculator->addTaxes(\Product::getPriceStatic($productObj->id, true, null, 2, null, false, true));
        }else{
            $productItem["price"] = \Product::getPriceStatic($productObj->id, true, null, 2, null, false, true);
        }

        $productAttributes = \Product::getProductAttributesIds($product_id);
        $productItem['totalVariants'] = 0;
        if($productAttributes != null){
            $productItem['totalVariants'] = count($productAttributes);
        }

        $combinations = $productObj->getAttributeCombinations($defaultLang, false);
        $productItem['totalVariants'] = count($combinations);

        $variants = [];
        foreach($productAttributes as $productAttribute) {

            $productAttributeObj = new \Combination((int)$productAttribute);
            $variant = [];


            $images = \Product::_getAttributeImageAssociations($productAttribute["id_product_attribute"]);
            if(count($images)>0){
                $image = new \Image((int)$images[0]);
                $variant['image'] = [
                    "url" => $linkObj->getImageLink($productObj->link_rewrite[$defaultLang], $image->id)
                ];
            }
            /*else{
                $variant['image'] = null;
            }*/


            $variant["metafields"] = [];

            $variant["displayName"] = $productObj->getProductName($productObj->id, $productAttribute["id_product_attribute"], $defaultLang);
            $variant["title"] = $variant["displayName"];
            $stockAvailableCombinationObj = new \StockAvailable(\StockAvailable::getStockAvailableIdByProductId($productObj->id, $productAttribute["id_product_attribute"]));
            $variant["inventoryQuantity"] = (int)$stockAvailableCombinationObj->quantity;

            if($taxCalculator != null){
                $variant["price"] = $taxCalculator->addTaxes(\Product::getPriceStatic($productObj->id, true, $productAttribute['id_product_attribute'], 2, null, false, true)); // With reductions
            }else{
                $variant["price"] = \Product::getPriceStatic($productObj->id, false, $productAttribute['id_product_attribute'], 2, null, false, true); // With reductions
            }
            $attributeCombinations = $productObj->getAttributeCombinationsById($productAttribute["id_product_attribute"], $defaultLang);
            $options = [];
            if (!empty($attributeCombinations)) {
                foreach ($attributeCombinations as $combination) {
                    if (isset($combination['group_name']) && isset($combination['attribute_name'])) {
                        $options[] = [
                            'name' => $combination['group_name'],
                            'value' => $combination['attribute_name'],
                        ];
                    }
                }
            }
            $variant["selectedOptions"] = $options;
            $variant["id"] = (int)$productAttribute["id_product_attribute"];
            $variants[] = $variant;
        }

        $productItem['variants'] = $variants;

        $images = [];
        $productImages = $productObj->getImages($defaultLang);

        $featuredImage = null;

        foreach ($productImages as $image) {
            // Get image URL
            $linkImage = $linkObj->getImageLink($productObj->link_rewrite[$defaultLang], $image['id_image'], 'large_default');

            if($image['cover'] != null && $image['cover']=='1'){
                $productItem['featuredImage'] = ['url'=>$linkImage];
            }
            $images[] = ['url'=>$linkImage];
        }
        $productItem["images"] = $images;
        $stockAvailableObj = new \StockAvailable(\StockAvailable::getStockAvailableIdByProductId($productObj->id));
        $productItem["totalInventory"] = (int)$stockAvailableObj->quantity;
        $productItem["status"] = $productObj->active?"ACTIVE":"NOT ACTIVE";

        $categories = $productObj->getCategories();
        $categoryItems = [];

        foreach ($categories as $categoryId) {
            $category = new \Category($categoryId, $defaultLang);

            if ($category->description !== null && $category->name !== null) {
                $categoryItems[] = [
                    "description" => $category->description,
                    "title" => $category->name
                ];
            }
        }

        $productItem["categories"] = $categoryItems;
        if($productObj->getTags($defaultLang) == ""){
            $productItem["tags"] = [];
        }else{
            $productItem["tags"] = explode(", ", $productObj->getTags($defaultLang));
        }

        $productFeatures = $productObj->getFrontFeatures($defaultLang);
        $productItem["metafields"] = [];
        foreach ($productFeatures as $feature) {
            $productItem["metafields"][] = [
            "name" => $feature['name'],
            "value" => $feature['value'] !== null ? $feature['value'] : ""
            ];
        }
        if($productItem['totalVariants']>0){
            $productItem["hasOnlyDefaultVariant"] = 0;
        }else{
            $productItem["hasOnlyDefaultVariant"] = 1;
        }
        $productItem["id"] = (int)$productObj->id;
        return $productItem;
    }

    public function getCatalogDataForBatch($batchSize, $idShop)
    {
        // Retrieve product IDs to process from askdialog_product table
        $products = \Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'askdialog_product WHERE id_shop = ' . $idShop . ' LIMIT ' . $batchSize);
        $defaultLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $linkObj = new \Link();
        foreach($products as $product){
            if (!empty($productData = $this->getProductData($product['id_product'], $defaultLang, $linkObj))) {
		        $this->products[] = $productData;
	        }
        }
        return $this->products;
    }

    public function getNumCatalogRemaining($idShop)
    {
        $totalProducts = \Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'askdialog_product WHERE id_shop = ' . (int)$idShop);
        return count($totalProducts);
    }

    public function getCatalogData(){
        $products = \Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'product');
        $defaultLang = (int) \Configuration::get('PS_LANG_DEFAULT');

        $linkObj = new \Link();
        foreach($products as $product){
            if (!empty($productData = $this->getProductData($product['id_product'], $defaultLang, $linkObj))) {
                $this->products[] = $productData;
			}

        }
        return $this->products;
    }

    public function getLanguageData(){
        $languages = \Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'lang');
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
