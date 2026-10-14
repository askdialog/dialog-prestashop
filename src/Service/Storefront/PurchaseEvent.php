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

namespace Dialog\AskDialog\Service\Storefront;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Builds the GA4 `purchase` parameters the storefront tag sends for an order.
 */
class PurchaseEvent
{
    /**
     * Order fields kept in `raw`: amounts and state, no customer or address data.
     */
    private const RAW_ORDER_FIELDS = [
        'id', 'reference', 'id_cart', 'id_currency', 'id_lang', 'id_shop', 'current_state', 'valid',
        'module', 'payment', 'conversion_rate', 'round_mode', 'round_type',
        'total_paid', 'total_paid_tax_incl', 'total_paid_tax_excl', 'total_paid_real',
        'total_products', 'total_products_wt',
        'total_discounts', 'total_discounts_tax_incl', 'total_discounts_tax_excl',
        'total_shipping', 'total_shipping_tax_incl', 'total_shipping_tax_excl', 'carrier_tax_rate',
        'total_wrapping', 'total_wrapping_tax_incl', 'total_wrapping_tax_excl',
        'date_add',
    ];

    /**
     * Order line columns kept in `raw`: what was sold, no cost price, supplier or download data.
     */
    private const RAW_LINE_FIELDS = [
        'id_order_detail', 'id_order', 'id_shop', 'product_id', 'product_attribute_id', 'id_customization',
        'product_name', 'product_reference', 'product_ean13', 'product_isbn', 'product_upc', 'product_mpn',
        'product_quantity', 'product_weight', 'product_price', 'original_product_price',
        'reduction_percent', 'reduction_amount', 'reduction_amount_tax_incl', 'reduction_amount_tax_excl',
        'group_reduction', 'product_quantity_discount', 'ecotax', 'tax_name', 'tax_rate',
        'unit_price_tax_incl', 'unit_price_tax_excl', 'total_price_tax_incl', 'total_price_tax_excl',
        'total_shipping_price_tax_incl', 'total_shipping_price_tax_excl',
    ];

    /**
     * A cart split across carriers becomes several orders sharing one reference: the purchase covers them all.
     *
     * @param \Order $order
     *
     * @return array GA4 purchase parameters: amounts in the order's currency, prices tax excluded
     */
    public static function fromOrder(\Order $order): array
    {
        $orders = [];
        foreach (\Order::getByReference($order->reference) as $sibling) {
            $orders[] = $sibling;
        }
        if ($orders === []) {
            $orders = [$order];
        }

        $lines = [];
        $cartRules = [];
        $tax = 0.0;
        $shipping = 0.0;
        foreach ($orders as $sibling) {
            $lines = array_merge($lines, $sibling->getOrderDetailList());
            $cartRules = array_merge($cartRules, $sibling->getCartRules());
            $tax += (float) $sibling->total_paid_tax_incl - (float) $sibling->total_paid_tax_excl;
            $shipping += (float) $sibling->total_shipping_tax_excl;
        }

        $items = [];
        $value = 0.0;
        foreach ($lines as $index => $line) {
            $items[] = self::item($line, $index, (int) $order->id_lang);
            $value += (float) $line['unit_price_tax_excl'] * (int) $line['product_quantity'];
        }

        $parameters = [
            'transaction_id' => (string) $order->reference,
            'currency' => (new \Currency((int) $order->id_currency))->iso_code,
            'value' => self::amount($value),
            'tax' => self::amount($tax),
            'shipping' => self::amount($shipping),
            'customer_type' => self::isFirstOrder($order) ? 'new' : 'returning',
            'items' => $items,
            'raw' => [
                'orders' => array_map(function (\Order $sibling) {
                    return self::pick(get_object_vars($sibling), self::RAW_ORDER_FIELDS);
                }, $orders),
                'order_details' => array_map(function (array $line) {
                    return self::pick($line, self::RAW_LINE_FIELDS);
                }, $lines),
                'cart_rules' => $cartRules,
            ],
        ];
        $coupons = self::couponCodes($cartRules);
        if ($coupons !== []) {
            $parameters['coupon'] = implode(',', $coupons);
        }

        return $parameters;
    }

    /**
     * @param array $line ps_order_detail row
     */
    private static function item(array $line, int $index, int $idLang): array
    {
        $idProduct = (int) $line['product_id'];
        $idProductAttribute = (int) $line['product_attribute_id'];
        $price = (float) $line['unit_price_tax_excl'];
        $item = [
            'item_id' => (string) $idProduct,
            'item_name' => (string) $line['product_name'],
            'price' => self::amount($price),
            'quantity' => (int) $line['product_quantity'],
            'index' => $index,
        ];

        $discount = (float) $line['original_product_price'] - $price;
        if ($discount > 0) {
            $item['discount'] = self::amount($discount);
        }

        $product = new \Product($idProduct, false, $idLang);
        if (\Validate::isLoadedObject($product)) {
            $item['item_name'] = (string) $product->name;
            if ($idProductAttribute > 0) {
                $variant = self::variantLabel((string) $line['product_name'], (string) $product->name);
                if ($variant !== null) {
                    $item['item_variant'] = $variant;
                }
            }
            $brand = \Manufacturer::getNameById((int) $product->id_manufacturer);
            if ($brand) {
                $item['item_brand'] = (string) $brand;
            }
            $category = new \Category((int) $product->id_category_default, $idLang);
            if (\Validate::isLoadedObject($category)) {
                $item['item_category'] = (string) $category->name;
            }
        }

        if ($idProductAttribute > 0) {
            $item['variant_id'] = (string) $idProductAttribute;
        }

        return $item;
    }

    /**
     * The order line names the product with its combination: "T-shirt (Size: M - Color: White)" from
     * PrestaShop 8, "T-shirt - Size : M, Color : White" before.
     */
    private static function variantLabel(string $lineName, string $productName): ?string
    {
        if ($productName === '' || strpos($lineName, $productName) !== 0) {
            return null;
        }
        $rest = substr($lineName, strlen($productName));
        if (strpos($rest, ' (') === 0 && substr($rest, -1) === ')') {
            $variant = substr($rest, 2, -1);
        } elseif (strpos($rest, ' - ') === 0) {
            $variant = substr($rest, 3);
        } else {
            return null;
        }

        return $variant === '' ? null : $variant;
    }

    /**
     * GA4 `coupon` is the code the shopper typed; automatic cart rules have none.
     *
     * @param array $cartRules ps_order_cart_rule rows
     *
     * @return string[]
     */
    private static function couponCodes(array $cartRules): array
    {
        $codes = [];
        foreach ($cartRules as $cartRule) {
            $code = (string) (new \CartRule((int) $cartRule['id_cart_rule']))->code;
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * New when no earlier valid order of this shop carries the customer's email: a guest checkout creates a
     * new guest account each time, and a failed payment is not a purchase.
     */
    private static function isFirstOrder(\Order $order): bool
    {
        $email = (new \Customer((int) $order->id_customer))->email;
        $earlierOrders = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = o.`id_customer`'
            . ' WHERE c.`email` = \'' . pSQL((string) $email) . '\''
            . ' AND o.`valid` = 1'
            . ' AND o.`id_shop` = ' . (int) $order->id_shop
            . ' AND o.`reference` <> \'' . pSQL((string) $order->reference) . '\''
            . ' AND o.`id_order` < ' . (int) $order->id
        );

        return $earlierOrders === 0;
    }

    private static function pick(array $row, array $fields): array
    {
        return array_intersect_key($row, array_flip($fields));
    }

    private static function amount(float $amount): float
    {
        return round($amount, 2);
    }
}
