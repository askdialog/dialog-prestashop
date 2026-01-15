<?php
/*
* 2007-2025 Dialog
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
*  @author Axel Paillaud <contact@axelweb.fr>
*  @copyright  2007-2025 Dialog
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Dialog\AskDialog\Repository\AppearanceRepository;
use Dialog\AskDialog\Service\AskDialogClient;
use Dialog\AskDialog\Service\DataGenerator;

class AskDialog extends Module
{
    public function __construct()
    {
        $this->name = 'askdialog';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'AskDialog';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.7',
            'max' => '8.99.99'
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Ask Dialog', [], 'Modules.Askdialog.Admin');
        $this->description =  $this->trans('Module to provide the AskDialog assistant on your e-shop', [], 'Modules.Askdialog.Admin');
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
            && \Configuration::updateValue('ASKDIALOG_API_URL', 'https://rtbzcxkmwj.execute-api.eu-west-1.amazonaws.com');
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
        // Register CSS files
        if ($this->context->controller instanceof \ProductController) {
            $this->context->controller->registerStylesheet(
                'module-askdialog-product-style',
                'modules/' . $this->name . '/views/css/cssForProductPage.css',
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

        // Register JS files
        $this->context->controller->registerJavascript(
            'module-askdialog-setupmodal',
            'modules/' . $this->name . '/views/js/setupModal.js',
            [
                'position' => 'bottom',
                'priority' => 200,
            ]
        );

        // Load specific JS for product pages
        if ($this->context->controller instanceof \ProductController) {
            $this->context->controller->registerJavascript(
                'module-askdialog-instant',
                'modules/' . $this->name . '/views/js/instant.js',
                [
                    'position' => 'bottom',
                    'priority' => 200,
                ]
            );
        } else {
            $this->context->controller->registerJavascript(
                'module-askdialog-ai-input',
                'modules/' . $this->name . '/views/js/ai-input.js',
                [
                    'position' => 'bottom',
                    'priority' => 200,
                ]
            );
        }

        $this->context->controller->registerJavascript(
            'module-askdialog-main',
            'modules/' . $this->name . '/views/js/askdialog.js',
            [
                'position' => 'bottom',
                'priority' => 200,
            ]
        );

        $this->context->controller->registerJavascript(
            'module-askdialog-posthog',
            'modules/' . $this->name . '/views/js/posthog.js',
            [
                'position' => 'bottom',
                'priority' => 200,
            ]
        );

        // Load PostHog order confirmation script on order confirmation page
        if ($this->context->controller instanceof \OrderConfirmationController) {
            $this->context->controller->registerJavascript(
                'module-askdialog-posthog-order',
                'modules/' . $this->name . '/views/js/posthog_order_confirmation.js',
                [
                    'position' => 'bottom',
                    'priority' => 200,
                ]
            );
        }
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
        if (!$this->context->controller instanceof \ProductController) {
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
            'suggestions' => ['suggestion-0', 'suggestion-1']
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayproduct.tpl');
    }

    public function hookDisplayFooterAfter($params)
    {
        $customer = $this->context->customer;
        $customerId = $customer->isLogged() ? $customer->id : 'anonymous';
        
        $publicApiKey = \Configuration::get('ASKDIALOG_API_KEY_PUBLIC');
        $countryCode = $this->context->country->iso_code;
        $languageCode = $this->context->language->iso_code;
        $languageName = $this->context->language->name;

        // Get appearance settings from database (JSON-based)
        $appearanceRepository = new AppearanceRepository();
        $idShop = (int) $this->context->shop->id;
        $appearanceSettings = $appearanceRepository->getSettings($idShop);

        // CDN URL for Dialog SDK
        $indexDotJsCdnUrl = 'https://d2zm7i5bmzo6ze.cloudfront.net/assets/index.js';

        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'customer_id' => $customerId,
            'public_api_key' => $publicApiKey,
            'country_code' => $countryCode,
            'language_code' => $languageCode,
            'language_name' => $languageName,
            'appearance_settings' => $appearanceSettings,
            'index_dot_js_cdn_url' => $indexDotJsCdnUrl,
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

        \Tools::redirectAdmin($route);
    }
}
