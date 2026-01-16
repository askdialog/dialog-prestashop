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
(function () {
  "use strict";

  const SHOPIFY_PATTERNS = {
    // Pattern: /apps/dialog/ai/product-questions?pagePath=X&locale=Y&productId=Z
    productQuestions: /\/apps\/dialog\/ai\/product-questions/,
  };

  /**
   * Rewrites Shopify API URLs to PrestaShop endpoints
   * @param {string} url - Original URL
   * @returns {string} - Rewritten URL or original if no match
   */
  function rewriteShopifyUrl(url) {
    // Handle product-questions endpoint
    if (SHOPIFY_PATTERNS.productQuestions.test(url)) {
      // Extract query parameters from original URL
      const urlObj = new URL(url, window.location.origin);
      const params = urlObj.searchParams.toString();

      const dialogApiEndpoint =
        "https://rtbzcxkmwj.execute-api.eu-west-1.amazonaws.com";

      // Build PrestaShop API endpoint
      return `${dialogApiEndpoint}/ai/product-questions?${params}`;
    }

    return url;
  }

  /**
   * Monkey-patch window.fetch
   */
  const originalFetch = window.fetch;
  window.fetch = function (resource, options) {
    let url = typeof resource === "string" ? resource : resource.url;
    const rewrittenUrl = rewriteShopifyUrl(url);

    if (rewrittenUrl !== url) {
      // Add Authorization header for PrestaShop API
      options = options || {};
      options.headers = options.headers || {};

      // Get public API key from Dialog global variables
      const publicApiKey = window.DIALOG_VARIABLES?.apiKey;
      if (publicApiKey) {
        // Ensure 'Token ' prefix (don't duplicate if already present)
        const authToken = publicApiKey.startsWith("Token ")
          ? publicApiKey
          : `Token ${publicApiKey}`;
        options.headers["Authorization"] = authToken;
      }

      resource =
        typeof resource === "string"
          ? rewrittenUrl
          : new Request(rewrittenUrl, resource);
    }

    return originalFetch.apply(this, [resource, options]);
  };

  /**
   * Monkey-patch XMLHttpRequest.prototype.open
   */
  const originalXHROpen = XMLHttpRequest.prototype.open;
  const originalXHRSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;

  XMLHttpRequest.prototype.open = function (
    method,
    url,
    async,
    user,
    password
  ) {
    const rewrittenUrl = rewriteShopifyUrl(url);

    // Store if this is a rewritten URL to add auth header later
    this._isRewrittenShopifyUrl = rewrittenUrl !== url;

    return originalXHROpen.apply(this, [
      method,
      rewrittenUrl,
      async,
      user,
      password,
    ]);
  };

  XMLHttpRequest.prototype.setRequestHeader = function (header, value) {
    // If this is a rewritten Shopify URL, add Authorization header
    if (this._isRewrittenShopifyUrl && !this._authHeaderSet) {
      const publicApiKey = window.DIALOG_VARIABLES?.apiKey;
      if (publicApiKey) {
        // Ensure 'Token ' prefix (don't duplicate if already present)
        const authToken = publicApiKey.startsWith("Token ")
          ? publicApiKey
          : `Token ${publicApiKey}`;
        originalXHRSetRequestHeader.call(this, "Authorization", authToken);
        this._authHeaderSet = true;
      }
    }

    return originalXHRSetRequestHeader.apply(this, [header, value]);
  };
})();
