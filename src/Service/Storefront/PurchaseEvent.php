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
     * @param \Order $order
     *
     * @return array GA4 purchase parameters: amounts in the order's currency, prices tax excluded
     */
    public static function fromOrder(\Order $order): array
    {
        $lines = $order->getOrderDetailList();
        $cartRules = $order->getCartRules();
        $items = [];
        foreach ($lines as $index => $line) {
            $items[] = self::item($line, $index, (int) $order->id_lang);
        }
        $value = 0.0;
        foreach ($lines as $line) {
            $value += (float) $line['unit_price_tax_excl'] * (int) $line['product_quantity'];
        }
        $coupons = array_filter(array_map(function ($cartRule) {
            return (string) $cartRule['name'];
        }, $cartRules));

        $parameters = [
            'transaction_id' => (string) $order->reference,
            'currency' => (new \Currency((int) $order->id_currency))->iso_code,
            'value' => self::amount($value),
            'tax' => self::amount((float) $order->total_paid_tax_incl - (float) $order->total_paid_tax_excl),
            'shipping' => self::amount((float) $order->total_shipping_tax_excl),
            'customer_type' => self::isFirstOrder($order) ? 'new' : 'returning',
            'items' => $items,
            'raw' => [
                'order' => self::rawOrder($order),
                // A downloadable product's hash opens its download link: it stays in the shop.
                'order_details' => array_map(function (array $line) {
                    unset($line['download_hash']);

                    return $line;
                }, $lines),
                'cart_rules' => $cartRules,
            ],
        ];
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
            // The order line names the product with its combination: "T-shirt (Size: M - Color: White)"
            // from PrestaShop 8, "T-shirt - Size : M, Color : White" before.
            if ($idProductAttribute > 0 && strpos($line['product_name'], $product->name) === 0) {
                $variant = trim(substr($line['product_name'], strlen($product->name)), ' -()');
                if ($variant !== '') {
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
     * New when no earlier order carries the customer's email: a guest checkout creates a new guest account each time.
     */
    private static function isFirstOrder(\Order $order): bool
    {
        $email = (new \Customer((int) $order->id_customer))->email;
        $earlierOrders = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` o'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.`id_customer` = o.`id_customer`'
            . ' WHERE c.`email` = \'' . pSQL((string) $email) . '\''
            . ' AND o.`id_order` < ' . (int) $order->id
        );

        return $earlierOrders === 0;
    }

    private static function rawOrder(\Order $order): array
    {
        $raw = [];
        foreach (self::RAW_ORDER_FIELDS as $field) {
            $raw[$field] = $order->{$field};
        }

        return $raw;
    }

    private static function amount(float $amount): float
    {
        return round($amount, 2);
    }
}
