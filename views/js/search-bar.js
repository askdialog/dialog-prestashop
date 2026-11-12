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

    // ps_searchbar binds a jQuery UI autocomplete to the header search input, and Dialog's suggestions
    // take its place. Disabled rather than destroyed: the theme passes it options again on a resize,
    // which would build a new one on a destroyed input. Disabled, it neither listens nor requests.
    function disableNativeSuggestions() {
        var $input = $('#search_widget input[name="s"]');
        if ($input.length === 0 || typeof $input.psBlockSearchAutocomplete !== 'function') {
            return;
        }
        try {
            $input.psBlockSearchAutocomplete('option', 'disabled', true);
        } catch (error) {
            // Not built on this input: nothing to disable
        }
    }

    if (typeof $ === 'function') {
        // Runs after ps_searchbar's own ready handler, which its earlier script registered first
        $(disableNativeSuggestions);
    }
})();
