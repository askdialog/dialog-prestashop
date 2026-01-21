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

{* CSS Custom Properties for user-defined appearance settings *}
{* Priority: User settings (via BO) > Theme overrides > Module defaults *}
<style id="askdialog-user-styles">
    :root {
        {if $appearance_settings.primary_color}
        --askdialog-primary-color: {$appearance_settings.primary_color} !important;
        {/if}
        {if $appearance_settings.background_color}
        --askdialog-bg-color: {$appearance_settings.background_color} !important;
        {/if}
        {if $appearance_settings.cta_text_color}
        --askdialog-cta-text-color: {$appearance_settings.cta_text_color} !important;
        {/if}
        {if $appearance_settings.cta_border_type}
        --askdialog-cta-border-type: {$appearance_settings.cta_border_type} !important;
        {/if}
        {if $appearance_settings.font_family}
        --askdialog-font-family: {$appearance_settings.font_family} !important;
        {/if}
    }
</style>

<div
    id="dialog-shopify-ai"
    data-user-id="{$customer_id}"
    data-api-key="{$public_api_key}"
    data-country-code="{$country_code}"
    data-shop-iso-code="{$language_code}"
    data-language="{$language_name}"
    data-locale="{$language_code}"
    data-primary-color="{$appearance_settings.primary_color}"
    data-background-color="{$appearance_settings.background_color}"
    data-cta-text-color="{$appearance_settings.cta_text_color}"
    data-cta-border-type="{$appearance_settings.cta_border_type}"
    data-capitalize-ctas="{if $appearance_settings.capitalize_ctas}1{else}0{/if}"
    data-font-family="{$appearance_settings.font_family}"
    data-highlight-product-name="{if $appearance_settings.highlight_product_name}1{else}0{/if}">
</div>

{* Load Dialog SDK with type="module" to prevent global scope pollution *}
<script type="module" src="{$index_dot_js_cdn_url}"></script>

<script>
    Object.assign(window, {
        DIALOG_VARIABLES: {
            apiKey: "{$public_api_key}",
            locale: "{$language_code}",
            primaryColor: "{$appearance_settings.primary_color}",
            ctaTextColor: "{$appearance_settings.cta_text_color}",
            capitalizeCtas: {if $appearance_settings.capitalize_ctas}true{else}false{/if},
            backgroundColor: "{$appearance_settings.background_color}",
            fontFamily: "{$appearance_settings.font_family}"
        }
    });
</script>
