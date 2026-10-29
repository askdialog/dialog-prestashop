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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Repository\CartOriginRepository;

/**
 * Cart handoff (DEC-3660 spike, prototype)
 *
 * GET /module/askdialog/handoff?p=<base64url JSON>&s=<hex HMAC-SHA256 of p>
 *
 * The payload is signed with the PRIVATE API key, which only Dialog's servers hold: the
 * public key is printed in every storefront page, so it cannot authenticate an origin.
 *
 * {"items":[{"id":12,"variant":34,"qty":2}],"origin":"dialog_plugin","ref":"<handoff id>","exp":1767225600}
 *
 * Adds each line to the visiting shopper's cart (created if they have none), skipping or
 * clamping what cannot be bought, records the origin on the cart, and redirects to the cart
 * page with a notice per line that did not go in as asked.
 */
class AskDialogHandoffModuleFrontController extends ModuleFrontController
{
    private const MAX_ITEMS = 50;
    private const MAX_QTY = 99;

    /** @var bool */
    public $ssl = true;

    public function postProcess()
    {
        $payload = $this->readPayload();
        if ($payload === null) {
            $this->errors[] = $this->trans('This cart link is invalid or has expired.', [], 'Modules.Askdialog.Shop');
            $this->redirectWithNotifications($this->cartUrl());

            return;
        }

        $cart = $this->ensureCart();
        $added = 0;
        foreach ($payload['items'] as $item) {
            if ($this->addLine($cart, $item)) {
                ++$added;
            }
        }

        if ($added > 0) {
            (new CartOriginRepository())->save((int) $cart->id, $payload['origin'], $payload['ref']);
        }

        $this->redirectWithNotifications($this->cartUrl());
    }

    /**
     * Decode and authenticate the payload
     *
     * @return array|null ['items' => [['id','variant','qty']], 'origin' => string, 'ref' => string]
     */
    private function readPayload(): ?array
    {
        $encoded = (string) Tools::getValue('p');
        $signature = (string) Tools::getValue('s');
        $secret = (string) Configuration::get('ASKDIALOG_API_KEY');

        if ($encoded === '' || $signature === '' || $secret === '') {
            return null;
        }
        if (!hash_equals(hash_hmac('sha256', $encoded, $secret), $signature)) {
            return null;
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
            return null;
        }
        if (!isset($data['exp']) || (int) $data['exp'] < time()) {
            return null;
        }

        $origin = isset($data['origin']) ? (string) $data['origin'] : '';
        if (!preg_match('/^[a-z0-9_]{1,32}$/', $origin)) {
            return null;
        }

        $items = [];
        foreach (array_slice($data['items'], 0, self::MAX_ITEMS) as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }
            $items[] = [
                'id' => (int) $item['id'],
                'variant' => isset($item['variant']) ? (int) $item['variant'] : 0,
                'qty' => max(1, min(self::MAX_QTY, isset($item['qty']) ? (int) $item['qty'] : 1)),
            ];
        }

        return $items === [] ? null : [
            'items' => $items,
            'origin' => $origin,
            'ref' => isset($data['ref']) ? substr((string) $data['ref'], 0, 64) : '',
        ];
    }

    /**
     * The visitor's cart, created and bound to their cookie when they have none yet
     * (same steps as CartController::processChangeProductInCart)
     */
    private function ensureCart(): Cart
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            $cart->add();
            $this->context->cookie->id_cart = (int) $cart->id;
            $this->context->cookie->write();
        }

        return $cart;
    }

    /**
     * Add one line, or explain to the shopper why it went in differently or not at all
     *
     * @param Cart $cart
     * @param array $item ['id', 'variant', 'qty']
     *
     * @return bool Whether anything was added
     */
    private function addLine(Cart $cart, array $item): bool
    {
        $idShop = (int) $this->context->shop->id;
        $product = new Product($item['id'], true, (int) $this->context->language->id, $idShop);

        if (!Validate::isLoadedObject($product) || !$product->active || !$product->available_for_order
            || !$product->isAssociatedToShop($idShop)) {
            $this->warning[] = $this->trans('A product from your selection is no longer available.', [], 'Modules.Askdialog.Shop');

            return false;
        }

        $name = $product->name;
        if ($product->customizable && !$product->hasAllRequiredCustomizableFields()) {
            $this->warning[] = $this->trans('%s needs to be personalised on its page before it can be added.', [$name], 'Modules.Askdialog.Shop');

            return false;
        }

        $idProductAttribute = $item['variant'];
        $minimalQuantity = (int) $product->minimal_quantity;
        if ($idProductAttribute > 0) {
            $combination = new Combination($idProductAttribute);
            if (!Validate::isLoadedObject($combination) || (int) $combination->id_product !== (int) $product->id
                || !$combination->isAssociatedToShop($idShop)) {
                $this->warning[] = $this->trans('The selected option of %s no longer exists.', [$name], 'Modules.Askdialog.Shop');

                return false;
            }
            $minimalQuantity = (int) $combination->minimal_quantity;
        } elseif ($product->hasAttributes()) {
            // A product with options cannot go in the cart without one: the shopper must choose
            $this->warning[] = $this->trans('Choose an option for %s on its page.', [$name], 'Modules.Askdialog.Shop');

            return false;
        }

        $inCart = $cart->getProductQuantity($product->id, $idProductAttribute);
        $alreadyInCart = (int) $inCart['quantity'];
        $quantity = $item['qty'];
        if ($alreadyInCart + $quantity < $minimalQuantity) {
            $quantity = $minimalQuantity - $alreadyInCart;
            $this->warning[] = $this->trans('%s is sold by %d at least: the quantity was raised.', [$name, $minimalQuantity], 'Modules.Askdialog.Shop');
        }

        if (!Product::isAvailableWhenOutOfStock(StockAvailable::outOfStock($product->id, $idShop))) {
            $available = (int) StockAvailable::getQuantityAvailableByProduct($product->id, $idProductAttribute, $idShop) - $alreadyInCart;
            if ($available < max(1, $minimalQuantity - $alreadyInCart)) {
                $this->warning[] = $this->trans('%s is out of stock.', [$name], 'Modules.Askdialog.Shop');

                return false;
            }
            if ($available < $quantity) {
                $quantity = $available;
                $this->warning[] = $this->trans('Only %d of %s were left: the quantity was lowered.', [$available, $name], 'Modules.Askdialog.Shop');
            }
        }

        $result = $cart->updateQty($quantity, $product->id, $idProductAttribute);
        if ($result !== true) {
            // false: not enough stock after all; -1: under the minimal quantity
            $this->warning[] = $this->trans('%s could not be added to your cart.', [$name], 'Modules.Askdialog.Shop');

            return false;
        }

        return true;
    }

    private function cartUrl(): string
    {
        return $this->context->link->getPageLink('cart', true, null, ['action' => 'show']);
    }
}
