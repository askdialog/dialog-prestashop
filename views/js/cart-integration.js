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
(function() {
    'use strict';

    /**
     * Add a product to the cart via AJAX
     *
     * @param {number} idProduct - Product ID
     * @param {number} idProductAttribute - Product attribute/combination ID (0 if no combination)
     * @param {number} quantity - Quantity to add
     */
    function askDialogAddToCart(idProduct, idProductAttribute, quantity) {
        if (typeof jQuery === 'undefined' || typeof prestashop === 'undefined') {
            console.error('[Dialog] jQuery or prestashop object not available');
            return;
        }

        jQuery.ajax({
            type: 'POST',
            dataType: 'text',
            headers: { 'cache-control': 'no-cache' },
            url: prestashop.urls.pages.cart,
            data: {
                controller: 'cart',
                add: 1,
                // As the theme's own add to cart: the JSON answer then carries the updated cart.
                action: 'update',
                ajax: true,
                qty: quantity,
                id_product: idProduct,
                token: prestashop.static_token,
                id_product_attribute: idProductAttribute
            },
            success: function(data) {
                var resp = {};
                try { resp = JSON.parse(data); } catch(e) {}

                // Emit PrestaShop event to update cart UI
                prestashop.emit('updateCart', {
                    reason: {
                        idProduct: idProduct,
                        idProductAttribute: idProductAttribute,
                        quantity: quantity,
                        idCustomization: 0,
                        token: prestashop.static_token,
                        action: 'add-to-cart'
                    },
                    resp: resp
                });
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('[Dialog] Add to cart error:', textStatus, errorThrown);
            }
        });
    }

    /**
     * Add the current product page's product to the cart
     * Uses form values from the product page
     */
    function askDialogAddCurrentProductToCart() {
        var idProduct = jQuery('#product_page_product_id').val();
        var idProductAttribute = jQuery('#idCombination').val() || 0;
        var quantity = jQuery('#quantity_wanted').val() || 1;

        askDialogAddToCart(idProduct, idProductAttribute, quantity);
    }

    // Expose functions to global scope for Dialog AI SDK
    window.askDialogAddToCart = askDialogAddToCart;
    window.askDialogAddCurrentProductToCart = askDialogAddCurrentProductToCart;

    // Storefront events: the cart steps, handed to the storefront tag in the GA4 contract.

    // The cart line fields kept in `raw`: a whole presented line carries images and
    // descriptions, too heavy for the tag's keepalive request (64 KB).
    var RAW_LINE_FIELDS = [
        'id_product', 'id_product_attribute', 'id_customization', 'name', 'reference',
        'ean13', 'upc', 'isbn', 'attributes', 'attributes_small', 'category',
        'id_manufacturer', 'manufacturer_name', 'quantity', 'price_amount',
        'regular_price_amount', 'has_discount', 'discount_type', 'discount_amount',
        'price_with_reduction', 'price_with_reduction_without_tax', 'total_wt'
    ];

    function rawLine(line) {
        var raw = {};
        RAW_LINE_FIELDS.forEach(function (field) {
            if (line[field] !== undefined) {
                raw[field] = line[field];
            }
        });

        return raw;
    }

    function rawCart(cart) {
        return {
            products: (cart.products || []).map(rawLine),
            products_count: cart.products_count,
            totals: cart.totals,
            subtotals: cart.subtotals,
            vouchers: cart.vouchers ? cart.vouchers.added : undefined,
            discounts: cart.discounts
        };
    }

    function roundAmount(amount) {
        return Math.round(amount * 100) / 100;
    }

    // A presented PrestaShop cart line as a GA4 item; `price` is the unit price shown, after discount.
    function lineItem(line, quantity) {
        var price = Number(line.price_amount);
        var regularPrice = Number(line.regular_price_amount);
        var variantId = Number(line.id_product_attribute);

        return {
            item_id: String(line.id_product),
            item_name: line.name || undefined,
            item_brand: line.manufacturer_name || undefined,
            item_category: line.category || undefined,
            item_variant: line.attributes_small || undefined,
            variant_id: variantId > 0 ? String(variantId) : undefined,
            price: price,
            discount: line.has_discount && regularPrice > price ? roundAmount(regularPrice - price) : undefined,
            quantity: quantity
        };
    }

    function itemsValue(items) {
        return roundAmount(items.reduce(function (sum, item) {
            return sum + item.price * item.quantity;
        }, 0));
    }

    function currencyIso() {
        return prestashop.currency && prestashop.currency.iso_code ? prestashop.currency.iso_code : undefined;
    }

    function sendStorefrontEvent(name, parameters) {
        if (typeof window.dialog === 'function') {
            window.dialog('event', name, parameters);
        }
    }

    function sameLine(line, idProduct, idProductAttribute, idCustomization) {
        return Number(line.id_product) === Number(idProduct)
            && Number(line.id_product_attribute || 0) === Number(idProductAttribute || 0)
            && Number(line.id_customization || 0) === Number(idCustomization || 0);
    }

    function lineKey(line) {
        return [line.id_product, line.id_product_attribute || 0, line.id_customization || 0].join(':');
    }

    // Line quantities of the last cart seen: the theme's add to cart answers with the line's
    // new total, so the quantity added is the difference.
    var knownLineQuantities = {};

    function rememberCart(cart) {
        knownLineQuantities = {};
        (cart && cart.products || []).forEach(function (line) {
            knownLineQuantities[lineKey(line)] = Number(line.quantity) || 0;
        });
    }

    // PrestaShop's front `updateCart` event, emitted by the theme after an add to cart
    // (`reason.linkAction`) and by askDialogAddToCart above (`reason.action`, with the quantity requested).
    function onUpdateCart(event) {
        var reason = event && event.reason;
        var resp = event && event.resp;
        if (!resp || resp.hasError || !resp.cart || !resp.cart.products) {
            return;
        }
        var previousQuantities = knownLineQuantities;
        rememberCart(resp.cart);
        if (!reason || (reason.linkAction !== 'add-to-cart' && reason.action !== 'add-to-cart')) {
            return;
        }
        var idProduct = resp.id_product || reason.idProduct;
        var idProductAttribute = resp.id_product_attribute || reason.idProductAttribute;
        var idCustomization = resp.id_customization || reason.idCustomization;
        var line = resp.cart.products.filter(function (candidate) {
            return sameLine(candidate, idProduct, idProductAttribute, idCustomization);
        })[0];
        if (!line) {
            return;
        }
        var quantity = reason.quantity !== undefined
            ? Number(reason.quantity)
            : Number(line.quantity) - (previousQuantities[lineKey(line)] || 0);
        if (!(quantity > 0)) {
            return;
        }
        var items = [lineItem(line, quantity)];
        sendStorefrontEvent('add_to_cart', {
            currency: currencyIso(),
            value: itemsValue(items),
            items: items,
            raw: {
                id_product: idProduct,
                id_product_attribute: idProductAttribute,
                id_customization: idCustomization,
                quantity: quantity,
                line: rawLine(line)
            }
        });
    }

    function cartParameters(cart) {
        var items = cart.products.map(function (line) {
            return lineItem(line, Number(line.quantity));
        });

        return {
            currency: currencyIso(),
            value: itemsValue(items),
            items: items,
            raw: rawCart(cart)
        };
    }

    // The checkout page reloads on every step: begin_checkout goes once per cart content and session.
    function isNewCheckout(cart) {
        var signature = cart.products.map(function (line) {
            return [line.id_product, line.id_product_attribute, line.id_customization, line.quantity].join(':');
        }).join('|');
        try {
            if (window.sessionStorage.getItem('dialog_begin_checkout') === signature) {
                return false;
            }
            window.sessionStorage.setItem('dialog_begin_checkout', signature);
        } catch (e) {}

        return true;
    }

    function sendCartPageEvent() {
        var pageName = prestashop.page && prestashop.page.page_name;
        var cart = prestashop.cart;
        if (!cart || !cart.products || cart.products.length === 0) {
            return;
        }
        if (pageName === 'cart') {
            sendStorefrontEvent('view_cart', cartParameters(cart));
        }
        if (pageName === 'checkout' && isNewCheckout(cart)) {
            sendStorefrontEvent('begin_checkout', cartParameters(cart));
        }
    }

    if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
        rememberCart(prestashop.cart);
        prestashop.on('updateCart', onUpdateCart);
        sendCartPageEvent();
    }
})();
