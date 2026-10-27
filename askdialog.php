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

/*
 * The module has no production Composer dependency (composer.json requires
 * only php>=7.2), so vendor/ holds nothing but a generated autoloader — and
 * source checkouts don't ship it (gitignored). Registering the PSR-4 mapping
 * directly lets the module boot from any distribution — S3 zip, git clone,
 * GitHub archive — with or without `composer install`. vendor/autoload.php is
 * still loaded when present so zips built with Composer behave identically.
 */
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    $prefix = 'Dialog\\AskDialog\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use Dialog\AskDialog\Form\GeneralDataConfiguration;
use Dialog\AskDialog\Helper\ContextHelper;
use Dialog\AskDialog\Helper\Logger;
use Dialog\AskDialog\Service\Export\ShopMarketMap;
use Dialog\AskDialog\Service\PostHogService;

class AskDialog extends Module
{
    /**
     * Dialog API base URL
     */
    private const DIALOG_API_URL = 'https://api.askdialog.ai';

    /**
     * Dialog's storefront tag: loads the assistant and the product block from the same CDN
     */
    private const DIALOG_TAG_URL = 'https://cdn.askdialog.com/assets/dialog.js';

    public function __construct()
    {
        $this->name = 'askdialog';
        $this->tab = 'front_office_features';
        $this->version = '1.5.0';
        $this->author = 'AskDialog';
        $this->need_instance = 0;
        // 1.7.6 and 1.7.7 behave identically here: both bundle Symfony 3.4, and
        // install, the widget hooks and the configuration page work the same on
        // either. (The HttpClient-backed services — export and PostHog — need
        // Symfony 4.3+, i.e. PrestaShop 8.0; that floor is unrelated to this
        // value and applies to 1.7.7 just as much. See CLAUDE.md.)
        $this->ps_versions_compliancy = [
            'min' => '1.7.6',
            'max' => '9.99.99',
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
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('displayOrderConfirmation')
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && Configuration::updateValue('ASKDIALOG_API_URL', self::DIALOG_API_URL)
            && Configuration::updateValue('ASKDIALOG_BATCH_SIZE', 5000)
            && Configuration::updateValue('ASKDIALOG_ENABLE_LOGS', false)
            && Configuration::updateValue('ASKDIALOG_AI_BUTTON_MODE', false);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && \Configuration::deleteByName('ASKDIALOG_API_URL')
            && \Configuration::deleteByName('ASKDIALOG_API_KEY')
            && \Configuration::deleteByName('ASKDIALOG_API_KEY_PUBLIC')
            && \Configuration::deleteByName('ASKDIALOG_ENABLE_PRODUCT_HOOK')
            && \Configuration::deleteByName('ASKDIALOG_ENABLE_LOGS')
            && \Configuration::deleteByName('ASKDIALOG_AI_BUTTON_MODE')
            && $this->uninstallDb();
    }

    public function hookActionFrontControllerSetMedia()
    {
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

        // The config the module's dialogLayer snippet hands to the storefront tag
        Media::addJsDef([
            'askDialogConfig' => $this->dialogConfig(),
            // Read by the widget to populate window.dialog.audit (DEC-2368)
            'DIALOG_VARIABLES' => [
                'integration' => [
                    'platform' => 'prestashop',
                    'moduleVersion' => $this->version,
                    'psVersion' => _PS_VERSION_,
                ],
            ],
        ]);

        $this->context->controller->registerJavascript(
            'module-askdialog-dialog-layer',
            'modules/' . $this->name . '/views/js/dialog-layer.js',
            [
                'position' => 'bottom',
                'priority' => 200,
            ]
        );

        // Version parameter forces cache invalidation when module is updated
        $this->context->controller->registerJavascript(
            'module-askdialog-dialog-tag',
            self::DIALOG_TAG_URL,
            [
                'position' => 'bottom',
                'priority' => 200,
                'server' => 'remote',
                'version' => $this->version,
                'attributes' => 'defer',
            ]
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

    /**
     * The storefront tag's config: the assistant's market, language and display currency
     *
     * @return array
     */
    private function dialogConfig(): array
    {
        $idShop = (int) $this->context->shop->id;

        // In multistore the currency is decided by the shop, not by the visitor:
        // reporting a geolocated country served by another shop would have the
        // assistant quote that shop's currency while the page shows this one's.
        $shopMarketMap = new ShopMarketMap();
        $countryCode = $shopMarketMap->boundCountryIso($idShop, $this->context->country->iso_code);

        // The visitor-selected display currency and its per-shop rate
        // (ps_currency_shop, relative to this shop's default currency). The
        // widget converts only base-currency amounts with it, so exposing the
        // base ISO alongside makes the rule self-contained.
        $currency = $this->context->currency;
        $idBaseCurrency = (int) ShopMarketMap::shopConfiguration('PS_CURRENCY_DEFAULT', $idShop);
        $baseCurrency = $idBaseCurrency > 0 ? new Currency($idBaseCurrency) : null;

        return [
            'apiKey' => (string) Configuration::get('ASKDIALOG_API_KEY_PUBLIC'),
            'assistant' => [
                'countryCode' => $countryCode,
                'locale' => $this->context->language->iso_code,
                'language' => $this->context->language->name,
                'displayCurrency' => [
                    'iso' => $currency !== null ? $currency->iso_code : '',
                    'rate' => $currency !== null ? (float) $currency->conversion_rate : 1.0,
                    'baseIso' => $baseCurrency !== null ? $baseCurrency->iso_code : '',
                ],
            ],
        ];
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['order'];
        $customer = $this->context->customer;

        // Track to PostHog (browser-side hook, cookies are available).
        // Analytics must never take down the order confirmation page, so this
        // catches Throwable, not Exception: a missing class raises an Error,
        // which an Exception catch lets through (that is exactly how a fatal
        // reached the front office once).
        try {
            $postHogService = new PostHogService();
            $postHogService->trackOrderConfirmation($order);
        } catch (Throwable $e) {
            Logger::log(
                '[AskDialog] PostHog trackOrderConfirmation error: ' . $e->getMessage(),
                3
            );
        }

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

        // The slot the storefront tag mounts the product block in, and the product and
        // combination it declares as the page's. The theme re-renders this hook on a
        // combination change, so the slot always carries the selected combination.
        $this->context->smarty->assign([
            'product_id' => (int) $product['id_product'],
            'selected_variant_id' => (int) $product['id_product_attribute'],
            'ai_button_mode' => (bool) Configuration::get(GeneralDataConfiguration::ASKDIALOG_AI_BUTTON_MODE),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayproduct.tpl');
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
        } catch (Throwable $e) {
            // Log error but don't break cart functionality. Throwable, not
            // Exception: a missing class raises an Error, which an Exception
            // catch lets through and turns into a fatal on the product page.
            Logger::log(
                '[AskDialog] PostHog trackAddToCart error: ' . $e->getMessage(),
                3
            );
        }
    }

}
