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

    var ATTACHED_ATTRIBUTE = 'data-dialog-search-results-attached';
    var HANDED_BACK_ATTRIBUTE = 'data-askdialog-search-handed-back';
    var root = document.documentElement;

    if (typeof MutationObserver !== 'function') {
        return;
    }

    // The tag removes its attribute when its first search fails and the native results are back in
    // #main: marking the hand-back stops search-page.css from hiding them for 3 s again. The callback
    // runs before the next paint, so they show in the frame of the hand-back.
    var observer = new MutationObserver(function() {
        if (!root.hasAttribute(ATTACHED_ATTRIBUTE)) {
            root.setAttribute(HANDED_BACK_ATTRIBUTE, '');
            observer.disconnect();
        }
    });
    observer.observe(root, { attributes: true, attributeFilter: [ATTACHED_ATTRIBUTE] });
})();
