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

<div
    id="dialog-shopify-ai"
    data-user-id="{$customer_id}"
    data-api-key="{$public_api_key}"
    data-country-code="{$country_code}"
    data-shop-iso-code="{$language_code}"
    data-language="{$language_name}"
    data-locale="{$language_code}"
    data-currency-iso="{$currency_iso}"
    data-currency-rate="{$currency_rate}"
    data-currency-base-iso="{$currency_base_iso}">
</div>

<div id="dialog-script" data-src="{$index_dot_js_cdn_url}"></div>

{* Load Dialog SDK with type="module" to prevent global scope pollution *}
<script type="module" src="{$index_dot_js_cdn_url}"></script>

<script>
    Object.assign(window, {
        DIALOG_VARIABLES: {
            apiKey: "{$public_api_key}",
            locale: "{$language_code}",
            displayCurrency: {
                iso: "{$currency_iso}",
                rate: {$currency_rate},
                baseIso: "{$currency_base_iso}"
            },
            {* Read by the widget to populate window.dialog.audit (DEC-2368) *}
            integration: {
                platform: "prestashop",
                moduleVersion: "{$module_version}",
                psVersion: "{$ps_version}"
            }
        }
    });
</script>
