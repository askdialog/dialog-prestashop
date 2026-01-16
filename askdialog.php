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

require_once __DIR__ . '/vendor/autoload.php';

use Dialog\AskDialog\Helper\ContextHelper;
use Dialog\AskDialog\Repository\AppearanceRepository;
use Dialog\AskDialog\Service\PostHogService;

class AskDialog extends Module
{
    /**
     * Dialog API base URL
     */
    private const DIALOG_API_URL = 'https://rtbzcxkmwj.execute-api.eu-west-1.amazonaws.com';

    /**
     * Dialog SDK CDN URL
     */
    private const DIALOG_SDK_CDN_URL = 'https://d2zm7i5bmzo6ze.cloudfront.net/assets/index.js';

    public function __construct()
    {
        $this->name = 'askdialog';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'AskDialog';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.7',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Ask Dialog', [], 'Modules.Askdialog.Admin');
        $this->description = $this->trans('Module to provide the AskDialog assistant on your e-shop', [], 'Modules.Askdialog.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    protected function installDb(): bool
    {
        $file = __DIR__ . '/sql/install.php';

        return is_file($file) ? (bool) require $file : false;
    }

    protected function uninstallDb(): bool
    {
        $file = __DIR__ . '/sql/uninstall.php';

        return is_file($file) ? (bool) require $file : false;
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayFooterAfter')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('actionFrontControllerInitBefore')
            && $this->registerHook('displayOrderConfirmation')
            && Configuration::updateValue('ASKDIALOG_API_URL', self::DIALOG_API_URL);
    }

    public function uninstall()
    {
        return parent::uninstall()
            // Commented for development comfort - uncomment in production to clean all configuration
            // && \Configuration::deleteByName('ASKDIALOG_API_URL')
            // && \Configuration::deleteByName('ASKDIALOG_API_KEY')
            // && \Configuration::deleteByName('ASKDIALOG_API_KEY_PUBLIC')
            // && \Configuration::deleteByName('ASKDIALOG_ENABLE_PRODUCT_HOOK')
            && $this->uninstallDb();
    }

    public function hookActionFrontControllerInitBefore()
    {
        // header("Access-Control-Allow-Origin: *");
        // header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        // header("Access-Control-Allow-Headers: Content-Type, Authorization");
        // if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        //     exit;
        // }
    }

    public function hookActionFrontControllerSetMedia()
    {
        // Register CSS files for all pages
        $this->context->controller->registerStylesheet(
            'module-askdialog-variables',
            'modules/' . $this->name . '/views/css/all-pages/variables.css',
            [
                'media' => 'all',
                'priority' => 200,
            ]
        );

        // Register CSS files specific to product pages
        if ($this->context->controller instanceof ProductController) {
            $this->context->controller->registerStylesheet(
                'module-askdialog-product-instant',
                'modules/' . $this->name . '/views/css/product-page/instant.css',
                [
                    'media' => 'all',
                    'priority' => 200,
                ]
            );
        }

        $this->context->controller->registerStylesheet(
            'module-askdialog-global-style',
            'modules/' . $this->name . '/views/css/cssForAllPages.css',
            [
                'media' => 'all',
                'priority' => 200,
            ]
        );

        // Register JS files from CDN
        // Note: CDN URLs are temporary and will be updated in future versions
        // Version parameter forces cache invalidation when module is updated
        $jsParams = [
            'position' => 'bottom',
            'priority' => 200,
            'server' => 'remote',
            'version' => $this->version,
            'attributes' => 'defer',
        ];

        // Shopify compatibility patch - MUST load before instant.js (product pages only)
        // This monkey-patches fetch/XMLHttpRequest to redirect Shopify API calls to PrestaShop endpoints
        // WARNING: If additional Shopify errors appear, abandon this approach and create native implementation
        if ($this->context->controller instanceof ProductController) {
            $this->context->controller->registerJavascript(
                'module-askdialog-shopify-compat-patch',
                'modules/' . $this->name . '/views/js/shopify-compat-patch.js',
                [
                    'position' => 'bottom',
                    'priority' => 190, // Must load BEFORE instant.js (priority 200)
                ]
            );
        }

        // setupModal.js - all pages
        $this->context->controller->registerJavascript(
            'module-askdialog-setupmodal',
            'https://cdn.shopify.com/extensions/019b7023-644d-7d8b-a5ac-a3e0723c9970/dialog-ai-app-290/assets/setupModal.js',
            $jsParams
        );

        // Load specific JS for product pages
        if ($this->context->controller instanceof ProductController) {
            // instant.js - product pages only
            $this->context->controller->registerJavascript(
                'module-askdialog-instant',
                'https://cdn.shopify.com/extensions/019b7023-644d-7d8b-a5ac-a3e0723c9970/dialog-ai-app-290/assets/instant.js',
                $jsParams
            );
        } else {
            // ai-input.js - all pages except product pages
            $this->context->controller->registerJavascript(
                'module-askdialog-ai-input',
                'https://cdn.shopify.com/extensions/019b7023-644d-7d8b-a5ac-a3e0723c9970/dialog-ai-app-290/assets/ai-input.js',
                $jsParams
            );
        }

        // index.js - all pages (main Dialog SDK from CDN)
        $this->context->controller->registerJavascript(
            'module-askdialog-index',
            self::DIALOG_SDK_CDN_URL,
            $jsParams
        );

        // cart-integration.js - PrestaShop-specific cart integration (must stay local)
        $this->context->controller->registerJavascript(
            'module-askdialog-cart-integration',
            'modules/' . $this->name . '/views/js/cart-integration.js',
            [
                'position' => 'bottom',
                'priority' => 200,
            ]
        );
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['order'];
        $customer = $this->context->customer;

        $this->context->smarty->assign([
            'order_reference' => $order->reference,
            'order_total' => $order->total_paid,
            'customer_name' => $customer->firstname . ' ' . $customer->lastname,
            'order_date' => $order->date_add,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayorderconfirmation.tpl');
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        // Ensure the hook is only executed on product pages
        if (!$this->context->controller instanceof ProductController) {
            return;
        }

        $enableProductHook = Configuration::get('ASKDIALOG_ENABLE_PRODUCT_HOOK') ? 1 : 0;
        if (!$enableProductHook) {
            return;
        }

        $product = $params['product'];

        $this->context->smarty->assign('product', $product);

        $product_id = $product['id_product'];
        $product_title = $product['name'];
        $product_slug = $product['link_rewrite'];
        $selected_variant_id = $product['id_product_attribute'];
        $assistant_name = $this->trans('AskDialog Assistant', [], 'Modules.Askdialog.Admin');
        $assistant_description = $this->trans('Comment puis-je vous aider avec ce produit ?', [], 'Modules.Askdialog.Admin');
        $ask_anything_placeholder = $this->trans('Comment puis-je vous aider avec ce produit ?', [], 'Modules.Askdialog.Admin');

        $this->context->smarty->assign([
            'product_id' => $product_id,
            'product_title' => $product_title,
            'product_slug' => $product_slug,
            'selected_variant_id' => $selected_variant_id,
            'assistant_name' => $assistant_name,
            'assistant_description' => $assistant_description,
            'ask_anything_placeholder' => $ask_anything_placeholder,
            'enableProductQuestion' => Configuration::get('ASKDIALOG_ENABLE_PRODUCT_QUESTION'),
            'defaultDesign' => Configuration::get('ASKDIALOG_DEFAULT_DESIGN'),
            'suggestions' => ['suggestion-0', 'suggestion-1'],
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayproduct.tpl');
    }

    public function hookDisplayFooterAfter($params)
    {
        $customer = $this->context->customer;
        $customerId = $customer->isLogged() ? $customer->id : 'anonymous';

        $publicApiKey = Configuration::get('ASKDIALOG_API_KEY_PUBLIC');
        $countryCode = $this->context->country->iso_code;
        $languageCode = $this->context->language->iso_code;
        $languageName = $this->context->language->name;

        // Get appearance settings from database (JSON-based)
        $appearanceRepository = new AppearanceRepository();
        $idShop = (int) $this->context->shop->id;
        $appearanceSettings = $appearanceRepository->getSettings($idShop);

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'customer_id' => $customerId,
            'public_api_key' => $publicApiKey,
            'country_code' => $countryCode,
            'language_code' => $languageCode,
            'language_name' => $languageName,
            'appearance_settings' => $appearanceSettings,
            'index_dot_js_cdn_url' => self::DIALOG_SDK_CDN_URL,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayfooterafter.tpl');
    }

    /**
     * Redirect to the module symfony configuration page
     *
     * @return void
     */
    public function getContent(): void
    {
        // Router service only available in PrestaShop >= 1.7.8
        if (version_compare(_PS_VERSION_, '1.7.8.0', '>=')) {
            $route = $this->get('router')->generate('askdialog_form_configuration');
        } else {
            $route = $this->context->link->getAdminLink('AdminAskDialog', true);
        }

        Tools::redirectAdmin($route);
    }

    /**
     * Hook: actionCartUpdateQuantityBefore
     *
     * Triggered before cart quantity is updated (product added/removed)
     * Tracks add to cart events to PostHog (increments only, not decrements)
     *
     * @param array $params Hook parameters
     */
    public function hookActionCartUpdateQuantityBefore($params)
    {
        // Only track additions (not removals)
        if (!isset($params['operator']) || $params['operator'] !== 'up') {
            return;
        }

        // Only track positive quantities
        $quantity = isset($params['quantity']) ? (int) $params['quantity'] : 0;
        if ($quantity <= 0) {
            return;
        }

        // Get product and cart
        $product = isset($params['product']) ? $params['product'] : null;
        $cart = isset($params['cart']) ? $params['cart'] : $this->context->cart;

        if (!$product || !Validate::isLoadedObject($product)) {
            return;
        }

        if (!$cart || !Validate::isLoadedObject($cart)) {
            return;
        }

        // Sync context cart to prevent bugs with uninitialized cart
        // (Context cart can be out of sync during hooks due to cookie/session timing)
        ContextHelper::syncContextCart($cart);

        // Get product attribute (combination ID)
        $idProductAttribute = isset($params['id_product_attribute']) ? (int) $params['id_product_attribute'] : 0;

        // Track to PostHog
        try {
            $postHogService = new PostHogService();
            $postHogService->trackAddToCart(
                (int) $product->id,
                $idProductAttribute,
                $quantity,
                $cart
            );
        } catch (Exception $e) {
            // Log error but don't break cart functionality
            PrestaShopLogger::addLog(
                'PostHog trackAddToCart error: ' . $e->getMessage(),
                3,
                null,
                'AskDialog'
            );
        }
    }

    /**
     * Hook: actionValidateOrder
     *
     * Triggered when an order is validated (payment confirmed)
     * Tracks order confirmation events to PostHog
     *
     * @param array $params Hook parameters containing order information
     */
    public function hookActionValidateOrder($params)
    {
        $order = isset($params['order']) ? $params['order'] : null;

        if (!$order || !Validate::isLoadedObject($order)) {
            return;
        }

        // Track to PostHog
        $postHogService = new PostHogService();
        $postHogService->trackOrderConfirmation($order);
    }
}
