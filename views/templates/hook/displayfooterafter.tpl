<div
    id="dialog-shopify-ai"
    data-user-id="{$customer_id}"
    data-api-key="{$public_api_key}"
    data-country-code="{$country_code}"
    data-shop-iso-code="{$language_code}"
    data-language="{$language_name}"
    data-locale="{$language_code}"
    data-primary-color="{$primary_color}"
    data-background-color="{$background_color}"
    data-cta-text-color="{$cta_text_color}"
    data-cta-border-type="{$cta_border_type}"
    data-capitalize-ctas="{$capitalize_ctas}"
    data-font-family="{$font_family}"
    data-highlight-product-name="{$highlight_product_name}"></div>

<script>
    Object.assign(window, {
        DIALOG_VARIABLES: {
            apiKey: "{$public_api_key}",
            locale: "{$language_code}",
            primaryColor: "{$primary_color}",
            ctaTextColor: "{$cta_text_color}",
            capitalizeCtas: "{$capitalize_ctas}",
            backgroundColor: "{$background_color}",
            fontFamily: "{$font_family}"
        }
    });
</script>

<script src="https://d2zm7i5bmzo6ze.cloudfront.net/assets/index.js" type="module" defer></script>