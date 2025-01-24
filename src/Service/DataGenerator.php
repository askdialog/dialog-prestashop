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

class DataGenerator{
    private $products = [];

    public function getProductData($product_id, $defaultLang, $linkObj) {
        $productObj = new Product((int)$product_id);
        $productItem = [];
        $publishedAt = (new \DateTime($productObj->date_add))->format('Y-m-d\TH:i:s\Z');
        $productItem["publishedAt"] = $publishedAt;
        $productItem["modifiedDescription"] = $productObj->description_short[$defaultLang];
        $productItem["description"] = $productObj->description[$defaultLang];
        $productItem["title"] = $productObj->name[$defaultLang];
        $productItem["handle"] = $productObj->link_rewrite[$defaultLang];

        //Retrieve variants 
        $combinations = $productObj->getAttributeCombinations($defaultLang, false);
        $productItem['totalVariants'] = count($combinations);

        $variants = [];
        foreach ($combinations as $combination) {
            $variant = [];
            $productAttributeObj = new ProductAttribute((int)$combination["id_product_attribute"]);
            $images = Product::_getAttributeImageAssociations($combination["id_product_attribute"]);
            if(count($images)>0){
                $image = new Image((int)$images[0]);
                $variant['image'] = [
                    "url" => $linkObj->getImageLink($productObj->link_rewrite[$defaultLang], $image->id)
                ];
            }else{
                $variant['image'] = [];
            }


            $variant["metafields"] = [];

            $variant["displayName"] = $productObj->getProductName($productObj->id, $combination["id_product_attribute"], $defaultLang);
            $variant["title"] = $variant["displayName"];
            $stockAvailableCombinationObj = new StockAvailable(StockAvailable::getStockAvailableIdByProductId($productObj->id, $combination["id_product_attribute"]));
            $variant["inventoryQuantity"] = $stockAvailableCombinationObj->quantity;
            $variant["price"] = $productObj->getPrice(false, $combination['id_product_attribute'], 2, null, false, true); //Avec réductions (computed)
            $variant["selectedOptions"] = [["name"=>"Taille", "value"=>"small"]];
            $variant["id"] = $combination["id_product_attribute"];
            $variant["compareAtPrice"] = $productObj->getPrice(false, $combination['id_product_attribute'], 2, null, false, false);  //Sans réductions
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
        $productItem["totalInventory"] = $stockAvailableObj->quantity;
        $productItem["status"] = $productObj->active?"ACTIVE":"NOT ACTIVE";

        //Retrieve categories
        $categories = $productObj->getCategories();
        $categoryItems = [];

        foreach ($categories as $categoryId) {
            $category = new Category($categoryId, $defaultLang);
            
            $categoryItems[] = [
                "description" => $category->description,
                "title" => $category->name
            ];
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
                "value" => $feature['value']
            ];
        }
        if($productItem['totalVariants']>0){
            $productItem["hasOnlyDefaultVariant"] = 0;
        }else{
            $productItem["hasOnlyDefaultVariant"] = 1;
        }
        $productItem["id"] = $productObj->id;
        return $productItem;
    }

    public function getCatalogData(){
        //Retrieve all prestashop products
        $products = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'product');
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $linkObj = new Link();
        foreach($products as $product){
            $this->products[] = $this->getProductData($product['id_product'], $defaultLang, $linkObj);
        }
        return $this->products;
    }


}