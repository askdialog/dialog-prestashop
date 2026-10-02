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
      Object.assign(window, {
        DIALOG_PRODUCT_VARIABLES: {
          productId: "{$product_id}",
          selectedVariantId: "{$selected_variant_id}"
        }
      });

      function registerVariantListener() {
        if (typeof prestashop !== 'undefined') {
          prestashop.on('updatedProduct', function(e) {
            var variantId = e.id_product_attribute || 0;

            window.DIALOG_PRODUCT_VARIABLES.selectedVariantId = variantId;

            var el = document.getElementById('dialog-shopify-ai-product');
            if (el) {
              el.setAttribute('data-selected-variant-id', variantId);
            }
          });
        }
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerVariantListener);
      } else {
        registerVariantListener();
      }
</script>

<div
    id="dialog-shopify-ai-product"
    data-product-id="{$product_id}"
    data-product-title="{$product_title}"
    data-handle="{$product_slug}"
    data-selected-variant-id="{$selected_variant_id}"></div>

<div class="dialog-instant" id="dialog-instant" data-product-id="{$product_id}"{if $ai_button_mode} data-ai-button-mode="true"{/if}>
    <div class="dialog-instant-text">
        <span id="assistant-name" class="dialog-question-text-title">
            {$assistant_name}
        </span>
        <span id="description" class="dialog-question-text-description">
            {$assistant_description}
        </span>
    </div>

    <div class="dialog-suggestion-wrapper">
        <div class="dialog-suggestions-container" id="dialog-suggestions-container">
        {foreach from=$suggestions item=suggestion}
            <button
                class="dialog-suggestion"
                id="dialog-{$suggestion}"
                type="button">
                {* ai icon processing *}
                <div class="dialog-suggestion-child">
                    <div class="dialog-suggestion-skeleton"></div>
                </div>
            </button>
        {/foreach}
        </div>
        {if $ai_button_mode}
            <button class="dialog-ask-something-else" type="button">
                {$ask_something_else_label}
            </button>
        {else}
            <div class="dialog-input-wrapper">
                <div class="dialog-input-container">
                    <input
                        id="dialog-ask-anything-input"
                        class="dialog-ask-anything-input"
                        placeholder="{$ask_anything_placeholder}">
                </div>
                <button
                    class="dialog-input-submit"
                    type="button"
                    disabled
                    id="send-message-button">
                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 20 20"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M10 16.6667V3.33334M10 3.33334L5 8.33334M10 3.33334L15 8.33334"
                            stroke="white"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        {/if}
    </div>
</div>
