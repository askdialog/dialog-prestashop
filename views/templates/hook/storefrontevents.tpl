{**
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
 *}

<script>
    (function (events, list) {
        window.dialogLayer = window.dialogLayer || [];
        (events || []).forEach(function (event) {
            window.dialogLayer.push(['event', event[0], event[1]]);
        });
        if (!list) {
            return;
        }
{literal}
        // Faceted search reloads the products through AJAX (filters, sort, pagination) without
        // rendering the footer again: the same view_item_list as StorefrontEvents::viewItemList.
        var RAW_FIELDS = ['id_product', 'id_product_attribute', 'name', 'reference', 'price_amount', 'price_tax_exc', 'price_with_reduction_without_tax', 'rate',
            'regular_price_amount', 'has_discount', 'discount_type', 'discount_percentage', 'discount_amount',
            'id_manufacturer', 'manufacturer_name', 'id_category_default', 'category_name', 'attributes'];
        var present = function (value) {
            return value !== undefined && value !== null && value !== '';
        };
        var round = function (amount) {
            var factor = Math.pow(10, list.precision);
            return Math.round(amount * factor) / factor;
        };
        var priceTaxExcluded = function (product) {
            if (present(product.price_tax_exc)) {
                return Number(product.price_tax_exc);
            }
            if (present(product.price_with_reduction_without_tax)) {
                return Number(product.price_with_reduction_without_tax);
            }
            var displayed = Number(product.price_amount) || 0;
            return list.prices_include_tax ? displayed / (1 + (Number(product.rate) || 0) / 100) : displayed;
        };
        var item = function (product, index) {
            var built = { item_id: String(product.id_product) };
            if (present(product.name)) { built.item_name = String(product.name); }
            if (Number(product.id_product_attribute) > 0) { built.variant_id = String(product.id_product_attribute); }
            if (present(product.manufacturer_name)) { built.item_brand = String(product.manufacturer_name); }
            if (present(product.category_name)) { built.item_category = String(product.category_name); }
            var variant = product.attributes && typeof product.attributes === 'object'
                ? Object.keys(product.attributes).map(function (group) { return product.attributes[group] && product.attributes[group].name; }).filter(present).join(' / ')
                : '';
            if (variant !== '') { built.item_variant = variant; }
            built.price = round(priceTaxExcluded(product));
            built.index = index;
            return built;
        };
        var raw = function (product) {
            var kept = {};
            RAW_FIELDS.forEach(function (field) {
                if (product[field] !== undefined && product[field] !== null) { kept[field] = product[field]; }
            });
            return kept;
        };
        var onProductList = function (data) {
            try {
                var products = (data && Array.isArray(data.products) ? data.products : []).filter(function (product) {
                    return product && present(product.id_product);
                });
                if (products.length === 0) {
                    return;
                }
                var shownFrom = data.pagination ? Number(data.pagination.items_shown_from) : 0;
                var firstIndex = shownFrom > 0 ? shownFrom - 1 : 0;
                window.dialogLayer.push(['event', 'view_item_list', {
                    item_list_id: list.item_list_id,
                    item_list_name: list.item_list_name,
                    currency: list.currency,
                    items: products.map(function (product, position) { return item(product, firstIndex + position); }),
                    raw: { item_list_id: list.item_list_id, item_list_name: list.item_list_name, products: products.map(raw) }
                }]);
            } catch (e) {
                // Analytics must never break the listing.
            }
        };
        var listen = function () {
            if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
                prestashop.on('updateProductList', onProductList);
            }
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', listen);
        } else {
            listen();
        }
{/literal}
    })({$storefront_events_json nofilter}, {$storefront_listing_json nofilter});
</script>
