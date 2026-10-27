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

    var PRODUCT_BLOCK_ID = 'dialog-product-block';

    window.dialogLayer = window.dialogLayer || [];

    // Queues a command for the storefront tag (cdn.askdialog.com/assets/dialog.js), loaded or not yet
    function dialog() {
        window.dialogLayer.push(arguments);
    }

    /**
     * The module's config, plus on a product page the product, its combination and the block's slot
     *
     * @returns {Object}
     */
    function pageConfig() {
        var config = Object.assign({}, window.askDialogConfig);
        var slot = document.getElementById(PRODUCT_BLOCK_ID);
        if (slot === null) {
            return config;
        }

        config.productId = slot.dataset.productId;
        // 0: the product has no combination
        if (slot.dataset.selectedVariantId !== '0') {
            config.variantId = slot.dataset.selectedVariantId;
        }
        config.productBlock = {
            selector: '#' + PRODUCT_BLOCK_ID,
            position: 'inside',
            aiButtonMode: slot.dataset.aiButtonMode === 'true',
            showDiagnostic: false
        };

        return config;
    }

    dialog('config', pageConfig());

    // The classic theme re-renders the product's additional info, slot included, on a combination
    // change; a theme that does not leaves the slot on the previous combination
    if (typeof prestashop !== 'undefined' && typeof prestashop.on === 'function') {
        prestashop.on('updatedProduct', function(event) {
            var slot = document.getElementById(PRODUCT_BLOCK_ID);
            if (slot !== null && event && event.id_product_attribute !== undefined) {
                slot.dataset.selectedVariantId = String(event.id_product_attribute);
            }
            dialog('config', pageConfig());
        });
    }
})();
