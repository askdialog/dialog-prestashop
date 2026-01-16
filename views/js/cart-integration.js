/**
 * Cart Integration for Dialog AI
 *
 * This script exposes functions to add products to the PrestaShop cart
 * when suggested by the Dialog AI assistant.
 *
 * Uses PrestaShop's native jQuery and prestashop global object.
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
            headers: { 'cache-control': 'no-cache' },
            url: prestashop.urls.pages.cart,
            data: {
                controller: 'cart',
                add: 1,
                ajax: true,
                qty: quantity,
                id_product: idProduct,
                token: prestashop.static_token,
                id_product_attribute: idProductAttribute
            },
            success: function(data) {
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
                    resp: data
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
})();
