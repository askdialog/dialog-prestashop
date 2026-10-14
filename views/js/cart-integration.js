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

    // A presented PrestaShop cart line as a GA4 item. Amounts are tax excluded, after discount, as in
    // every storefront event of the module; the displayed ones stay in `raw`.
    function lineItem(line, quantity) {
        var price = Number(line.price_with_reduction_without_tax);
        var shownPrice = Number(line.price_amount);
        var shownRegularPrice = Number(line.regular_price_amount);
        var variantId = Number(line.id_product_attribute);
        // The shown discount ratio, applied to the tax-excluded price.
        var discount = line.has_discount && shownRegularPrice > shownPrice && shownPrice > 0
            ? roundAmount(price * (shownRegularPrice / shownPrice - 1))
            : undefined;

        return {
            item_id: String(line.id_product),
            item_name: line.name || undefined,
            item_brand: line.manufacturer_name || undefined,
            item_category: line.category || undefined,
            item_variant: line.attributes_small || undefined,
            variant_id: variantId > 0 ? String(variantId) : undefined,
            price: roundAmount(price),
            discount: discount,
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

    function lineKey(line) {
        return [line.id_product, line.id_product_attribute || 0, line.id_customization || 0].join(':');
    }

    // Line quantities of the last cart seen, the fallback when the add request was not seen.
    var knownLineQuantities = {};

    function rememberCart(cart) {
        knownLineQuantities = {};
        (cart && cart.products || []).forEach(function (line) {
            knownLineQuantities[lineKey(line)] = Number(line.quantity) || 0;
        });
    }

    // Quantities requested by the cart requests still waiting for their `updateCart`: an add
    // (theme, quick view, assistant) or a "+" on the cart page. A decrease is not an add.
    var pendingAdds = [];

    function requestParameters(settings) {
        var query = String(settings.url || '').split('?')[1] || '';
        var data = typeof settings.data === 'string' ? settings.data : '';
        var parameters = {};
        (query + '&' + data).split('&').forEach(function (pair) {
            var parts = pair.split('=');
            if (parts[0]) {
                parameters[decodeURIComponent(parts[0])] = decodeURIComponent((parts[1] || '').replace(/\+/g, ' '));
            }
        });

        return parameters;
    }

    function isCartRequest(url, parameters) {
        var cartUrl = prestashop.urls && prestashop.urls.pages && prestashop.urls.pages.cart;

        return parameters.controller === 'cart'
            || (typeof cartUrl === 'string' && String(url).split('?')[0] === cartUrl.split('?')[0]);
    }

    function onAjaxSend(event, xhr, settings) {
        var parameters = requestParameters(settings);
        if (!isCartRequest(settings.url, parameters) || !parameters.id_product) {
            return;
        }
        var isAdd = parameters.add === '1' || (parameters.update === '1' && parameters.op === 'up');
        if (!isAdd) {
            return;
        }
        pendingAdds.push({ idProduct: Number(parameters.id_product), quantity: Number(parameters.qty) || 1 });
    }

    function takePendingAdd(idProduct) {
        for (var i = 0; i < pendingAdds.length; i++) {
            if (pendingAdds[i].idProduct === Number(idProduct)) {
                return pendingAdds.splice(i, 1)[0];
            }
        }

        return null;
    }

    // The quantity PrestaShop actually added: the one requested, never more than the line holds
    // nor than its growth since the last cart seen (when that cart is not stale, i.e. it grew).
    function addedQuantity(requested, line, previousQuantity) {
        var lineQuantity = Number(line.quantity);
        var growth = lineQuantity - previousQuantity;
        if (requested === null) {
            return growth;
        }
        var added = Math.min(requested, lineQuantity);

        return growth > 0 ? Math.min(added, growth) : added;
    }

    // PrestaShop's front `updateCart` event: its response carries the updated cart, whatever
    // emitted it (theme add to cart, cart page quantity, askDialogAddToCart above).
    function onUpdateCart(event) {
        var reason = event && event.reason || {};
        var resp = event && event.resp;
        if (!resp || resp.hasError || !resp.cart || !resp.cart.products || !resp.id_product) {
            return;
        }
        var previousQuantities = knownLineQuantities;
        rememberCart(resp.cart);
        var pending = takePendingAdd(resp.id_product);
        var isAdd = pending !== null || reason.linkAction === 'add-to-cart' || reason.action === 'add-to-cart';
        if (!isAdd) {
            return;
        }
        var line = resp.cart.products.filter(function (candidate) {
            return lineKey(candidate) === lineKey(resp);
        })[0];
        if (!line) {
            return;
        }
        var quantity = addedQuantity(pending ? pending.quantity : null, line, previousQuantities[lineKey(line)] || 0);
        if (!(quantity > 0)) {
            return;
        }
        var items = [lineItem(line, quantity)];
        sendStorefrontEvent('add_to_cart', {
            currency: currencyIso(),
            value: itemsValue(items),
            items: items,
            raw: {
                id_product: resp.id_product,
                id_product_attribute: resp.id_product_attribute,
                id_customization: resp.id_customization,
                quantity_requested: pending ? pending.quantity : undefined,
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

    // The checkout page reloads on every step: begin_checkout goes once per cart and content.
    function isNewCheckout(cart) {
        var marker = [window.askdialogCartId].concat(cart.products.map(function (line) {
            return lineKey(line) + 'x' + line.quantity;
        })).join('|');
        try {
            if (window.sessionStorage.getItem('dialog_begin_checkout') === marker) {
                return false;
            }
            window.sessionStorage.setItem('dialog_begin_checkout', marker);
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
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ajaxSend(onAjaxSend);
        }
        prestashop.on('updateCart', onUpdateCart);
        sendCartPageEvent();
    }
})();
