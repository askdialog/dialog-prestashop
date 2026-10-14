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
 *
 * Prices are tax excluded and after discount, as in every event of the contract; the displayed price stays in `raw`.
 * The script of views/templates/hook/storefrontevents.tpl builds the same items from the AJAX listing reloads.
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
        'price_tax_exc',
        'rate',
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
     * @param array $prices currency, precision, prices_include_tax
     */
    public static function viewItem($product, array $prices): array
    {
        $item = self::item($product, $prices);

        return [
            'currency' => $prices['currency'],
            'value' => $item['price'],
            'items' => [$item],
            'raw' => ['product' => self::rawProduct($product)],
        ];
    }

    /**
     * @param array $list item_list_id, item_list_name
     * @param iterable $products the presented products of the listing page
     * @param int $firstIndex position of the first product in the whole list, 0 on the first page
     * @param array $prices currency, precision, prices_include_tax
     */
    public static function viewItemList(array $list, $products, int $firstIndex, array $prices): array
    {
        $items = [];
        $rawProducts = [];
        $index = $firstIndex;
        foreach ($products as $product) {
            $items[] = self::item($product, $prices) + ['index' => $index++];
            $rawProducts[] = self::rawProduct($product);
        }

        return $list + [
            'currency' => $prices['currency'],
            'items' => $items,
            'raw' => $list + ['products' => $rawProducts],
        ];
    }

    /**
     * GA4 indexes count from 0 across pages; PrestaShop's items_shown_from counts from 1.
     *
     * @param array|\ArrayAccess $pagination
     */
    public static function firstIndex($pagination): int
    {
        return isset($pagination['items_shown_from']) ? max(0, (int) $pagination['items_shown_from'] - 1) : 0;
    }

    /**
     * @param array|\ArrayAccess $product
     */
    private static function item($product, array $prices): array
    {
        $item = ['item_id' => (string) $product['id_product']];
        $name = (string) self::field($product, 'name');
        if ($name !== '') {
            $item['item_name'] = $name;
        }
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
        $item['price'] = round(self::priceTaxExcluded($product, $prices), $prices['precision']);

        return $item;
    }

    /**
     * The price after discount, tax excluded: PrestaShop's price_tax_exc, or the displayed price without its tax rate.
     *
     * @param array|\ArrayAccess $product
     */
    private static function priceTaxExcluded($product, array $prices): float
    {
        if (isset($product['price_tax_exc'])) {
            return (float) $product['price_tax_exc'];
        }
        $displayed = (float) self::field($product, 'price_amount');

        return $prices['prices_include_tax'] ? $displayed / (1 + (float) self::field($product, 'rate') / 100) : $displayed;
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
