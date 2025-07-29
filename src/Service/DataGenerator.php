<?php

namespace LouisAuthie\Askdialog\Service;

use Db;
use Product;
use Configuration;
use Link;
use Category;
use Image;
use ProductAttribute;
use StockAvailable;
use Address;
use Country;
use TaxManagerFactory;
use Combination;


class DataGenerator{
    private $products = [];

    public function generateCMSData()
    {
        // Récupérer toutes les pages CMS
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'cms_lang WHERE id_lang = ' . (int)Configuration::get('PS_LANG_DEFAULT');
        $cmsPages = Db::getInstance()->executeS($sql);

        $cmsData = [];
        foreach ($cmsPages as $page) {
            $cmsData[] = [
                'title' => $page['meta_title'],
                'content' => $page['content']
            ];
        }

        // Créer le dossier temp s'il n'existe pas
        if (!file_exists(_PS_MODULE_DIR_ . 'askdialog/temp')) {
            mkdir(_PS_MODULE_DIR_ . 'askdialog/temp', 0777, true);
        }

        // Générer le fichier JSON
        $tempFile = _PS_MODULE_DIR_ . 'askdialog/temp/cms.json';
        file_put_contents($tempFile, json_encode($cmsData));
    }

    public function getProductData($product_id, $defaultLang, $linkObj, $countryCode = 'fr') {
        $productObj = new Product((int)$product_id);

        if (!Validate::isLoadedObject($productObj)) {
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
        //Handle the product price with tax in the country
        if($countryCode != null){
            $addressObj = new Address();
            //Get the Country ID from the country code in iso format
            $countryObj = new Country();
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
            $taxManager = TaxManagerFactory::getManager($addressObj, $type);
            $taxCalculator = $taxManager->getTaxCalculator();
            $productItem["price"] = $taxCalculator->addTaxes(Product::getPriceStatic($productObj->id, true, null, 2, null, false, true));
        }else{
            $productItem["price"] = Product::getPriceStatic($productObj->id, true, null, 2, null, false, true);
        }

        $productAttributes = Product::getProductAttributesIds($product_id);
        $productItem['totalVariants'] = 0;
        if($productAttributes != null){
            $productItem['totalVariants'] = count($productAttributes);
        }

        //Retrieve variants 
        $combinations = $productObj->getAttributeCombinations($defaultLang, false);
        $productItem['totalVariants'] = count($combinations);

        $variants = [];
        foreach($productAttributes as $productAttribute) {

            $productAttributeObj = new Combination((int)$productAttribute);
            $variant = [];
            

            $images = Product::_getAttributeImageAssociations($productAttribute["id_product_attribute"]);
            if(count($images)>0){
                $image = new Image((int)$images[0]);
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
            $stockAvailableCombinationObj = new StockAvailable(StockAvailable::getStockAvailableIdByProductId($productObj->id, $productAttribute["id_product_attribute"]));
            $variant["inventoryQuantity"] = (int)$stockAvailableCombinationObj->quantity;
            
            if($taxCalculator != null){
                $variant["price"] = $taxCalculator->addTaxes(Product::getPriceStatic($productObj->id, true, $productAttribute['id_product_attribute'], 2, null, false, true)); // With reductions (computed)
            }else{
                $variant["price"] = Product::getPriceStatic($productObj->id, false, $productAttribute['id_product_attribute'], 2, null, false, true); // With reductions (computed)
            }
            $attributeCombinations = $productObj->getAttributeCombinationsById($productAttribute["id_product_attribute"], $defaultLang);
            $options = [];
            if (!empty($attributeCombinations)) {
                foreach ($attributeCombinations as $combination) {
                    if (isset($combination['group_name']) && isset($combination['attribute_name'])) {
                        $options[] = [
                            'name' => $combination['group_name'],  // ex: Couleur, Taille
                            'value' => $combination['attribute_name'], // ex: Bleu, M
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
            //Get image url
            $linkImage = $linkObj->getImageLink($productObj->link_rewrite[$defaultLang], $image['id_image'], 'large_default');

            if($image['cover'] != null && $image['cover']=='1'){
                $productItem['featuredImage'] = ['url'=>$linkImage];
            }
            $images[] = ['url'=>$linkImage];           
        }
        $productItem["images"] = $images;
        $stockAvailableObj = new StockAvailable(StockAvailable::getStockAvailableIdByProductId($productObj->id));
        $productItem["totalInventory"] = (int)$stockAvailableObj->quantity;
        $productItem["status"] = $productObj->active?"ACTIVE":"NOT ACTIVE";

        //Retrieve categories
        $categories = $productObj->getCategories();
        $categoryItems = [];

        foreach ($categories as $categoryId) {
            $category = new Category($categoryId, $defaultLang);
            
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

        //Get all the product features
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
        //Retrieve from the askdialog_product table the ids to process
        $products = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'askdialog_product WHERE id_shop = ' . $idShop . ' LIMIT ' . $batchSize);
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT'); 
        $linkObj = new Link();
        foreach($products as $product){
            if (!empty($productData = $this->getProductData($product['id_product'], $defaultLang, $linkObj))) {
		        $this->products[] = $productData;
	        }

        }
        return $this->products;
    }

    public function getNumCatalogRemaining($idShop)
    {
        $totalProducts = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'askdialog_product WHERE id_shop = ' . (int)$idShop);
        return count($totalProducts);
    }

    public function getCatalogData(){
        //Retrieve all prestashop products
        $products = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'product');
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $linkObj = new Link();
        foreach($products as $product){
            if (!empty($productData = $this->getProductData($product['id_product'], $defaultLang, $linkObj))) {
                $this->products[] = $productData;
			}

        }
        return $this->products;
    }

    public function getLanguageData(){
        $languages = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'lang');
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
