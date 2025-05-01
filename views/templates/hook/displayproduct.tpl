<script>
      Object.assign(window, {
        DIALOG_PRODUCT_VARIABLES: {
          productId: "{$product_id}",
          selectedVariantId: "{$selected_variant_id}"
        }
      });
</script>

<div
    id="dialog-shopify-ai-product"
    data-product-id="{$product_id}"
    data-product-title="{$product_title}"
    data-handle="{$product_slug}"
    data-selected-variant-id="{$selected_variant_id}" top></div>

<div class="dialog-instant" id="dialog-instant">
    <div class="dialog-instant-text">
        <span id="assistant-name" class="dialog-question-text-title">
            {$assistant_name}
        </span>
        {if $enableProductQuestion}
            <span id="description" class="dialog-question-text-description">
                {$assistant_description}
            </span>
        {/if}
    </div>

    <div class="dialog-suggestion-wrapper">
        {if true || $enableProductQuestion}
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
        {/if}
        {if $defaultDesign || true}
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
        {else}
            <div class="dialog-input-wrapper dialog-ai-input-squared">
                <div class="dialog-input-container">
                    {* ai icon processing *}
                    <input
                        id="dialog-ask-anything-input"
                        class="dialog-ask-anything-input"
                        placeholder="{$ask_anything_placeholder}">
                </div>
                <button
                    class="dialog-input-submit dialog-input-submit-squared"
                    type="button"
                    disabled
                    id="send-message-button">
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M3.16367 0.130033C3.368 -0.05572 3.68422 -0.0406617 3.86997 0.163667L8.86997 5.66367C9.04335 5.85438 9.04335 6.14563 8.86997 6.33634L3.86997 11.8363C3.68422 12.0407 3.368 12.0557 3.16367 11.87C2.95934 11.6842 2.94428 11.368 3.13003 11.1637L7.82427 6L3.13003 0.83634C2.94428 0.632011 2.95934 0.315787 3.16367 0.130033Z" fill="white"></path>
                  </svg>
                </button>
            </div>
        {/if}
    </div>
</div>

<script>
    // On product page, PrestaShop 1.6 reloads the page when the variant (attribute) is changed

    // Function to update the variant ID after page reload
    function updateVariantId() {

        var productDetails = document.querySelector('#product-details');
        jsonData = JSON.parse(productDetails.getAttribute('data-product'));
        
        var variantId = jsonData.id_product_attribute;
        var dialogProduct = document.querySelector('#dialog-shopify-ai-product');
        dialogProduct.setAttribute('data-selected-variant-id', variantId);
        window.DIALOG_PRODUCT_VARIABLES.selectedVariantId = variantId;
    }

    // Run the function on page load to set the initial variant ID
    updateVariantId();

    // Listen for the DOMContentLoaded event to ensure the DOM is fully loaded after page reload
    document.addEventListener('DOMContentLoaded', function () {
        updateVariantId();
    });

    //If any event of click on #attributes
    document.querySelectorAll('#dialog-instant').forEach(function (element) {
        element.addEventListener('mouseenter', function () {
            updateVariantId();
        });
    });
</script>


