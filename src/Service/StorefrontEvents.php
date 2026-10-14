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
/**
 * Builds the GA4 commerce events the module hands to the storefront tag, from the products PrestaShop presents to its templates.
 */
class StorefrontEvents
{
    /**
     * PrestaShop fields kept in `raw`, so that a translation can be corrected in dbt afterwards.
     */
    private const RAW_PRODUCT_FIELDS = [
        'id_product',
        'id_product_attribute',
        'name',
        'reference',
        'price_amount',
        'regular_price_amount',
        'has_discount',
        'discount_type',
        'discount_percentage',
        'discount_amount',
        'id_manufacturer',
        'manufacturer_name',
        'id_category_default',
        'category_name',
        'attributes',
    ];

    /**
     * @param array|\ArrayAccess $product the presented product of the product page
     */
    public static function viewItem($product, string $currency): array
    {
        $item = self::item($product);

        return [
            'currency' => $currency,
            'value' => $item['price'],
            'items' => [$item],
            'raw' => ['product' => self::rawProduct($product)],
        ];
    }

    /**
     * @param iterable $products the presented products of the listing page
     * @param int $firstIndex position of the first product in the whole list, 0 on the first page
     */
    public static function viewItemList(string $listId, string $listName, $products, int $firstIndex): array
    {
        $items = [];
        $rawProducts = [];
        $index = $firstIndex;
        foreach ($products as $product) {
            $items[] = self::item($product) + ['index' => $index++];
            $rawProducts[] = self::rawProduct($product);
        }

        return [
            'item_list_id' => $listId,
            'item_list_name' => $listName,
            'items' => $items,
            'raw' => ['list_id' => $listId, 'list_name' => $listName, 'products' => $rawProducts],
        ];
    }

    /**
     * @param array|\ArrayAccess $product
     */
    private static function item($product): array
    {
        $item = [
            'item_id' => (string) $product['id_product'],
            'item_name' => (string) $product['name'],
        ];
        $variantId = (int) self::field($product, 'id_product_attribute');
        if ($variantId > 0) {
            $item['variant_id'] = (string) $variantId;
        }
        $brand = (string) self::field($product, 'manufacturer_name');
        if ($brand !== '') {
            $item['item_brand'] = $brand;
        }
        $category = (string) self::field($product, 'category_name');
        if ($category !== '') {
            $item['item_category'] = $category;
        }
        $variant = self::variantLabel(self::field($product, 'attributes'));
        if ($variant !== '') {
            $item['item_variant'] = $variant;
        }
        // The price the shopper sees, after discount.
        $item['price'] = round((float) self::field($product, 'price_amount'), 2);

        return $item;
    }

    /**
     * The combination's attribute values, as in "S / White".
     *
     * @param mixed $attributes id_attribute_group => ['group' => ..., 'name' => ...]
     */
    private static function variantLabel($attributes): string
    {
        if (!is_array($attributes)) {
            return '';
        }
        $names = [];
        foreach ($attributes as $attribute) {
            if (is_array($attribute) && isset($attribute['name']) && $attribute['name'] !== '') {
                $names[] = (string) $attribute['name'];
            }
        }

        return implode(' / ', $names);
    }

    /**
     * @param array|\ArrayAccess $product
     */
    private static function rawProduct($product): array
    {
        $raw = [];
        foreach (self::RAW_PRODUCT_FIELDS as $field) {
            if (isset($product[$field])) {
                $raw[$field] = $product[$field];
            }
        }

        return $raw;
    }

    /**
     * A presented product answers [] for a field it lacks, so a missing field reads as null here.
     *
     * @param array|\ArrayAccess $product
     *
     * @return mixed
     */
    private static function field($product, string $field)
    {
        return isset($product[$field]) ? $product[$field] : null;
    }
}
