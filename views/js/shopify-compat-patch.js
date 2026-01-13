/**
 * Shopify Compatibility Monkey Patch
 * 
 * This script intercepts fetch/XMLHttpRequest calls made by Dialog's Shopify CDN scripts
 * and redirects them to PrestaShop-compatible endpoints.
 * 
 * IMPORTANT: This is a temporary compatibility layer. If additional Shopify-specific
 * errors appear beyond the initial JSON.parse issue, this approach should be abandoned
 * in favor of a fully PrestaShop-native implementation.
 * 
 * Execution order: Must load BEFORE instant.js from CDN
 */
(function() {
    'use strict';

    const SHOPIFY_PATTERNS = {
        // Pattern: /apps/dialog/ai/product-questions?pagePath=X&locale=Y&productId=Z
        productQuestions: /\/apps\/dialog\/ai\/product-questions/
    };

    /**
     * Rewrites Shopify API URLs to PrestaShop endpoints
     * @param {string} url - Original URL
     * @returns {string} - Rewritten URL or original if no match
     */
    function rewriteShopifyUrl(url) {
        // Handle product-questions endpoint
        if (SHOPIFY_PATTERNS.productQuestions.test(url)) {
            console.log('[AskDialog] Intercepted Shopify API call:', url);
            
            // Extract query parameters from original URL
            const urlObj = new URL(url, window.location.origin);
            const params = urlObj.searchParams;
            
            // Get product ID from Dialog global variables or URL params
            const productId = params.get('productId') || window.DIALOG_PRODUCT_VARIABLES?.productId || '';
            
            // Build PrestaShop API endpoint
            // Note: This returns suggestions data - adjust if your API structure differs
            const prestashopUrl = `${window.location.origin}/module/askdialog/api?action=getProductData&id_product=${productId}`;
            
            console.log('[AskDialog] Rewriting to PrestaShop endpoint:', prestashopUrl);
            return prestashopUrl;
        }

        return url;
    }

    /**
     * Monkey-patch window.fetch
     */
    const originalFetch = window.fetch;
    window.fetch = function(resource, options) {
        let url = typeof resource === 'string' ? resource : resource.url;
        const rewrittenUrl = rewriteShopifyUrl(url);
        
        if (rewrittenUrl !== url) {
            // Add Authorization header for PrestaShop API
            options = options || {};
            options.headers = options.headers || {};
            
            // Get public API key from Dialog global variables
            const publicApiKey = window.DIALOG_VARIABLES?.apiKey;
            if (publicApiKey) {
                options.headers['Authorization'] = `Token ${publicApiKey}`;
            }
            
            resource = typeof resource === 'string' ? rewrittenUrl : new Request(rewrittenUrl, resource);
        }
        
        return originalFetch.apply(this, [resource, options]);
    };

    /**
     * Monkey-patch XMLHttpRequest.prototype.open
     */
    const originalXHROpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        const rewrittenUrl = rewriteShopifyUrl(url);
        return originalXHROpen.apply(this, [method, rewrittenUrl, async, user, password]);
    };

    console.log('[AskDialog] Shopify compatibility patch loaded successfully');
})();
