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

use Dialog\AskDialog\Helper\Logger;
use Dialog\AskDialog\Repository\CartOriginRepository;
use Dialog\AskDialog\Service\CartHandoff\HandoffPayload;
use Dialog\AskDialog\Service\CartHandoff\InvalidHandoffException;

/**
 * Class AskDialogHandoffModuleFrontController
 *
 * GET index.php?fc=module&module=askdialog&controller=handoff&p=<payload>&s=<signature>
 *
 * Rebuilds a cart Dialog handed over in a signed link (HandoffPayload): adds each line to
 * the visitor's cart, created if they have none, skipping or clamping what cannot be bought
 * with a notice, then redirects to the cart page with the link's UTM parameters. A link is
 * claimed in the cart before its lines go in, so following it again adds nothing.
 */
class AskDialogHandoffModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    public function postProcess()
    {
        // The link adds to a cart: neither the browser nor a proxy may answer it again
        header('Cache-Control: no-store');

        try {
            $payload = HandoffPayload::verify(
                (string) Tools::getValue('p'),
                (string) Tools::getValue('s'),
                (string) Configuration::get('ASKDIALOG_API_KEY'),
                time()
            );
        } catch (InvalidHandoffException $e) {
            Logger::log('[AskDialog] Handoff: link refused - ' . $e->getMessage(), Logger::WARNING);
            $this->errors[] = $this->trans('This cart link is invalid or has expired.', [], 'Modules.Askdialog.Shop');
            $this->redirectToCart([]);

            return;
        }

        $cart = $this->ensureCart();
        $origins = new CartOriginRepository();
        try {
            $claimed = $origins->claim((int) $cart->id, $payload->getRef(), $payload->getOrigin());
        } catch (Throwable $e) {
            Logger::log('[AskDialog] Handoff: could not claim the link - ' . $e->getMessage(), Logger::ERROR);
            $this->errors[] = $this->trans('Your selection could not be added to your cart.', [], 'Modules.Askdialog.Shop');
            $this->redirectToCart($payload->getUtm());

            return;
        }

        // Already claimed: a reload or a second click, whose lines are in the cart already
        if ($claimed && $this->addLines($cart, $payload->getLines()) === 0) {
            $this->release($origins, (int) $cart->id, $payload->getRef());
        }

        $this->redirectToCart($payload->getUtm());
    }

    /**
     * A claim left behind makes the link add nothing when followed again: the failure is logged,
     * the shopper already has the notices of the lines that did not go in
     */
    private function release(CartOriginRepository $origins, int $idCart, string $ref)
    {
        try {
            $released = $origins->release($idCart, $ref);
        } catch (Throwable $e) {
            $released = false;
        }
        if (!$released) {
            Logger::log('[AskDialog] Handoff: could not release the claim of cart ' . $idCart, Logger::ERROR);
        }
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
     * @param array<int, array{id: int, variant: int, qty: int}> $lines
     *
     * @return int How many lines went in
     */
    private function addLines(Cart $cart, array $lines): int
    {
        $added = 0;
        foreach ($lines as $line) {
            try {
                if ($this->addLine($cart, $line)) {
                    ++$added;
                }
            } catch (Throwable $e) {
                Logger::log('[AskDialog] Handoff: could not add product ' . $line['id'] . ' - ' . $e->getMessage(), Logger::ERROR);
                $this->warning[] = $this->trans('A product from your selection could not be added to your cart.', [], 'Modules.Askdialog.Shop');
            }
        }

        return $added;
    }

    /**
     * Add one line, or explain to the shopper why it went in differently or not at all
     *
     * @param array{id: int, variant: int, qty: int} $line
     *
     * @return bool Whether anything was added
     */
    private function addLine(Cart $cart, array $line): bool
    {
        $idShop = (int) $this->context->shop->id;
        $product = new Product($line['id'], true, (int) $this->context->language->id, $idShop);

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

        $idProductAttribute = $line['variant'];
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
        $quantity = $line['qty'];
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

    /**
     * Redirects to the cart page, never the checkout (DEC-3659), with the notices of the request
     *
     * @param array<string, string> $utm
     */
    private function redirectToCart(array $utm)
    {
        $this->redirectWithNotifications(
            $this->context->link->getPageLink('cart', true, null, ['action' => 'show'] + $utm)
        );
    }
}
