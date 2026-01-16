<?php
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

namespace Dialog\AskDialog\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Context Helper
 *
 * Provides utility methods to ensure PrestaShop Context is properly synchronized
 */
class ContextHelper
{
    /**
     * Ensure Context::cart is properly loaded from the given Cart
     *
     * This prevents bugs when accessing the cart during hooks where Context
     * might not be synchronized yet (e.g., actionCartUpdateQuantityBefore).
     *
     * Note: PrestaShop's Context cart can be out of sync with the actual cart
     * due to cookie/session timing issues.
     *
     * @param \Cart $cart The cart to sync with Context
     *
     * @return void
     */
    public static function syncContextCart(\Cart $cart)
    {
        $context = \Context::getContext();

        // If Context cart is not loaded OR has a different ID, reload it
        if (!\Validate::isLoadedObject($context->cart) || $context->cart->id != $cart->id) {
            $context->cart = new \Cart($cart->id);
        }
    }
}
