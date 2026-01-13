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

use Dialog\AskDialog\Service\AskDialogClient;
use Dialog\AskDialog\Service\PostHogService;
use Dialog\AskDialog\Helper\ContextHelper;

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
            'min' => '1.7.8',
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
            && $this->registerHook('actionCartUpdateQuantityBefore')
            && $this->registerHook('actionValidateOrder')
            && $this->setDefaultConfigurationValues();
    }

    private function setDefaultConfigurationValues()
    {
        return Configuration::updateValue('ASKDIALOG_API_URL', 'https://rtbzcxkmwj.execute-api.eu-west-1.amazonaws.com') // Dialog API base URL
            && Configuration::updateValue('ASKDIALOG_COLOR_PRIMARY', '#CCCCCC') // Default primary color
            && Configuration::updateValue('ASKDIALOG_COLOR_BACKGROUND', '#FFFFFF') // Default background color
            && Configuration::updateValue('ASKDIALOG_COLOR_CTA_TEXT', '#000000') // Default CTA text color
            && Configuration::updateValue('ASKDIALOG_CTA_BORDER_TYPE', 'solid') // Default border type
            && Configuration::updateValue('ASKDIALOG_CAPITALIZE_CTAS', 0) // Default boolean value for capitalizing CTAs
            && Configuration::updateValue('ASKDIALOG_FONT_FAMILY', 'Arial, sans-serif') // Default font family
            && Configuration::updateValue('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME', 0) // Default boolean value for highlighting product name
            && Configuration::updateValue('ASKDIALOG_BATCH_SIZE', 1000000); // Default batch size
    }

    public function uninstall()
    {
        return parent::uninstall()
            // Commented for development comfort - uncomment in production to clean all configuration
            // && Configuration::deleteByName('ASKDIALOG_API_URL')
            // && Configuration::deleteByName('ASKDIALOG_API_KEY')
            // && Configuration::deleteByName('ASKDIALOG_API_KEY_PUBLIC')
            // && Configuration::deleteByName('ASKDIALOG_ENABLE_PRODUCT_HOOK')
            // && Configuration::deleteByName('ASKDIALOG_COLOR_PRIMARY')
            // && Configuration::deleteByName('ASKDIALOG_COLOR_BACKGROUND')
            // && Configuration::deleteByName('ASKDIALOG_COLOR_CTA_TEXT')
            // && Configuration::deleteByName('ASKDIALOG_CTA_BORDER_TYPE')
            // && Configuration::deleteByName('ASKDIALOG_CAPITALIZE_CTAS')
            // && Configuration::deleteByName('ASKDIALOG_FONT_FAMILY')
            // && Configuration::deleteByName('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME')
            // && Configuration::deleteByName('ASKDIALOG_BATCH_SIZE')
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
        // Register CSS files (local files)
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

        // Register JS files from CDN
        // Note: CDN URLs are temporary and will be updated in future versions
        // Version parameter forces cache invalidation when module is updated
        $jsParams = [
            'position' => 'bottom',
            'priority' => 200,
            'server' => 'remote',
            'version' => $this->version,
            'attributes' => 'defer'
        ];

        // Shopify compatibility patch - MUST load before instant.js (product pages only)
        // This monkey-patches fetch/XMLHttpRequest to redirect Shopify API calls to PrestaShop endpoints
        if ($this->context->controller instanceof \ProductController) {
            $this->context->controller->registerJavascript(
                'module-askdialog-shopify-compat-patch',
                'modules/' . $this->name . '/views/js/shopify-compat-patch.js',
                [
                    'position' => 'bottom',
                    'priority' => 190,
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
        if ($this->context->controller instanceof \ProductController) {
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
            'https://d2zm7i5bmzo6ze.cloudfront.net/assets/index.js',
            $jsParams
        );

        // askdialog.js - PrestaShop-specific cart integration (must stay local)
        $this->context->controller->registerJavascript(
            'module-askdialog-main',
            'modules/' . $this->name . '/views/js/askdialog.js',
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
        //Include view
        $this->context->smarty->assign('module_dir', $this->_path);
        $customer = $this->context->customer;
        $customerId = $customer->isLogged() ? $customer->id : 'anonymous';
        $this->context->smarty->assign('customer_id', $customerId);
        $publicApiKey = Configuration::get('ASKDIALOG_API_KEY_PUBLIC');
        $countryCode = $this->context->country->iso_code;
        $languageCode = $this->context->language->iso_code;

        $languageName = $this->context->language->name;
        $primaryColor = Configuration::get('ASKDIALOG_COLOR_PRIMARY');
        $backgroundColor = Configuration::get('ASKDIALOG_COLOR_BACKGROUND');
        $ctaTextColor = Configuration::get('ASKDIALOG_COLOR_CTA_TEXT');
        $ctaBorderType = Configuration::get('ASKDIALOG_CTA_BORDER_TYPE');
        $capitalizeCtas = Configuration::get('ASKDIALOG_CAPITALIZE_CTAS');
        $fontFamily = Configuration::get('ASKDIALOG_FONT_FAMILY');
        $highlightProductName = Configuration::get('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME');

        $this->context->smarty->assign([
            'public_api_key' => $publicApiKey,
            'country_code' => $countryCode,
            'language_code' => $languageCode,
            'language_name' => $languageName,
            'primary_color' => $primaryColor,
            'background_color' => $backgroundColor,
            'cta_text_color' => $ctaTextColor,
            'cta_border_type' => $ctaBorderType,
            'capitalize_ctas' => $capitalizeCtas,
            'font_family' => $fontFamily,
            'highlight_product_name' => $highlightProductName
        ]);
        return $this->display(__FILE__, 'views/templates/hook/displayfooterafter.tpl');

    }

    public function getContent()
    {
        $output = '';

        //Si on est a l'étape 1 on affiche le formulaire de configuration des API Keys
        if (Tools::getValue('test') == null && (Tools::getValue('step') == 1 || Tools::getValue('step') == null)) {
            if (Tools::isSubmit('submit' . $this->name)) {
                $apiKey = strval(Tools::getValue('ASKDIALOG_API_KEY'));
                $apiKeyPublic = strval(Tools::getValue('ASKDIALOG_API_KEY_PUBLIC'));
                $enableProductHook = (bool)Tools::getValue('ASKDIALOG_ENABLE_PRODUCT_HOOK');
                if (!$apiKey || empty($apiKey)) {
                    $output .= $this->displayError($this->trans('Invalid API Key', [], 'Modules.Askdialog.Admin'));
                } else {
                    Configuration::updateValue('ASKDIALOG_API_KEY', $apiKey);
                    Configuration::updateValue('ASKDIALOG_API_KEY_PUBLIC', $apiKeyPublic);
                    Configuration::updateValue('ASKDIALOG_ENABLE_PRODUCT_HOOK', $enableProductHook);

                    $output .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Askdialog.Admin'));
                    $apiClient = new AskDialogClient();
                    $result = $apiClient->sendDomainHost();
                    if ($result) {
                        $output .= $this->displayConfirmation($this->trans('Connection successful', [], 'Modules.Askdialog.Admin'));
                    } else {
                        $output .= $this->displayError($this->trans('Connection failed', [], 'Modules.Askdialog.Admin'));
                    }
                }
            }
            return $output . $this->renderFormApiKeys();
        } else if (Tools::getValue('step') == 2 ) {
            if (Tools::isSubmit('submit' . $this->name)) {
                $primaryColor = strval(Tools::getValue('ASKDIALOG_COLOR_PRIMARY'));
                $backgroundColor = strval(Tools::getValue('ASKDIALOG_COLOR_BACKGROUND'));
                $ctaTextColor = strval(Tools::getValue('ASKDIALOG_COLOR_CTA_TEXT'));
                $ctaBorderType = strval(Tools::getValue('ASKDIALOG_CTA_BORDER_TYPE'));
                $capitalizeCtas = strval(Tools::getValue('ASKDIALOG_CAPITALIZE_CTAS'));
                $fontFamily = strval(Tools::getValue('ASKDIALOG_FONT_FAMILY'));
                $highlightProductName = strval(Tools::getValue('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME'));
                $batchSize = strval(Tools::getValue('ASKDIALOG_BATCH_SIZE'));

                Configuration::updateValue('ASKDIALOG_COLOR_PRIMARY', $primaryColor);
                Configuration::updateValue('ASKDIALOG_COLOR_BACKGROUND', $backgroundColor);
                Configuration::updateValue('ASKDIALOG_COLOR_CTA_TEXT', $ctaTextColor);
                Configuration::updateValue('ASKDIALOG_CTA_BORDER_TYPE', $ctaBorderType);
                Configuration::updateValue('ASKDIALOG_CAPITALIZE_CTAS', $capitalizeCtas);
                Configuration::updateValue('ASKDIALOG_FONT_FAMILY', $fontFamily);
                Configuration::updateValue('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME', $highlightProductName);
                Configuration::updateValue('ASKDIALOG_BATCH_SIZE', $batchSize);
                $output .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Askdialog.Admin'));
            }
            return $output . $this->renderFormSetting();
        }
    }

    protected function renderFormApiKeys()
    {
        $fieldsForm =
        [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Askdialog.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('API Key public', [], 'Modules.Askdialog.Admin'),
                        'name' => 'ASKDIALOG_API_KEY_PUBLIC',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                    'type' => 'text',
                    'label' => $this->trans('API Key private', [], 'Modules.Askdialog.Admin'),
                    'name' => 'ASKDIALOG_API_KEY',
                    'size' => 20,
                    'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable on Product Page', [], 'Modules.Askdialog.Admin'),
                        'name' => 'ASKDIALOG_ENABLE_PRODUCT_HOOK',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', [], 'Admin.Global')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', [], 'Admin.Global')
                            ]
                        ],
                        'desc' => $this->trans('Enable or disable the AskDialog assistant on the product page.', [], 'Modules.Askdialog.Admin')
                    ],
                    //add a link to dialog onboarding
                    [
                        'type' => 'html',
                        'name' => 'askdialog_onboarding',
                        'html_content' => '<a href="https://app.askdialog.com/onboarding" target="_blank" class="btn btn-info">Go to AskDialog onboarding</a>'
                    ]

                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
                //Add a link button to go to the next step
                'buttons' => [
                    [
                        'href' => $this->context->link->getAdminLink('AdminModules', false)
                            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name.'&step=2&token='.Tools::getAdminTokenLite('AdminModules'),
                        'title' => $this->trans('Next', [], 'Modules.Askdialog.Admin'),
                        'class' => 'btn btn-default pull-right',
                        'icon' => 'process-icon-next'
                    ]
                ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name.'&step=1';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesApiKeys(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    protected function renderFormSetting()
    {
        $fieldsForm = [
            'form' => [
            'legend' => [
                'title' => $this->trans('Settings', [], 'Modules.Askdialog.Admin'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                'type' => 'color',
                'label' => $this->trans('Primary Color', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_COLOR_PRIMARY',
                'size' => 20,
                'required' => true,
                ],
                [
                'type' => 'color',
                'label' => $this->trans('Background Color', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_COLOR_BACKGROUND',
                'size' => 20,
                'required' => true,
                ],
                [
                'type' => 'color',
                'label' => $this->trans('CTA Text Color', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_COLOR_CTA_TEXT',
                'size' => 20,
                'required' => true,
                ],
                [
                'type' => 'text',
                'label' => $this->trans('CTA Border Type', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_CTA_BORDER_TYPE',
                'size' => 20,
                'required' => true,
                ],
                [
                'type' => 'switch',
                'label' => $this->trans('Capitalize CTAs', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_CAPITALIZE_CTAS',
                'is_bool' => true,
                'values' => [
                    [
                    'id' => 'active_on',
                    'value' => 1,
                    'label' => $this->trans('Enabled', [], 'Admin.Global')
                    ],
                    [
                    'id' => 'active_off',
                    'value' => 0,
                    'label' => $this->trans('Disabled', [], 'Admin.Global')
                    ]
                ],
                ],
                [
                'type' => 'text',
                'label' => $this->trans('Font Family', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_FONT_FAMILY',
                'size' => 20,
                'required' => true,
                ],
                [
                'type' => 'switch',
                'label' => $this->trans('Highlight Product Name', [], 'Modules.Askdialog.Admin'),
                'name' => 'ASKDIALOG_HIGHLIGHT_PRODUCT_NAME',
                'is_bool' => true,
                'values' => [
                    [
                    'id' => 'active_on',
                    'value' => 1,
                    'label' => $this->trans('Enabled', [], 'Admin.Global')
                    ],
                    [
                    'id' => 'active_off',
                    'value' => 0,
                    'label' => $this->trans('Disabled', [], 'Admin.Global')
                    ]
                ],
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Batch Size', [], 'Modules.Askdialog.Admin'),
                    'name' => 'ASKDIALOG_BATCH_SIZE',
                    'size' => 20,
                    'required' => true,
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save', [], 'Admin.Actions'),
            ],
            'buttons' => [
                    [
                        'href' => $this->context->link->getAdminLink('AdminModules', false)
                            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name.'&step=1&token='.Tools::getAdminTokenLite('AdminModules'),
                        'title' => $this->trans('Previous', [], 'Modules.Askdialog.Admin'),
                        'class' => 'btn btn-default pull-left',
                        'icon' => 'process-icon-back'
                    ]
            ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name.'&step=2';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValuesSettings(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    protected function getConfigFormValuesApiKeys()
    {
        return [
            'ASKDIALOG_API_KEY' => Configuration::get('ASKDIALOG_API_KEY', ''),
            'ASKDIALOG_API_KEY_PUBLIC' => Configuration::get('ASKDIALOG_API_KEY_PUBLIC', ''),
            'ASKDIALOG_ENABLE_PRODUCT_HOOK' => Configuration::get('ASKDIALOG_ENABLE_PRODUCT_HOOK') ? 1 : 0,
        ];
    }

    protected function getConfigFormValuesSettings()
    {
        return [
            'ASKDIALOG_COLOR_PRIMARY' => Configuration::get('ASKDIALOG_COLOR_PRIMARY', ''),
            'ASKDIALOG_COLOR_BACKGROUND' => Configuration::get('ASKDIALOG_COLOR_BACKGROUND', ''),
            'ASKDIALOG_COLOR_CTA_TEXT' => Configuration::get('ASKDIALOG_COLOR_CTA_TEXT', ''),
            'ASKDIALOG_CTA_BORDER_TYPE' => Configuration::get('ASKDIALOG_CTA_BORDER_TYPE', ''),
            'ASKDIALOG_CAPITALIZE_CTAS' => Configuration::get('ASKDIALOG_CAPITALIZE_CTAS', false),
            'ASKDIALOG_FONT_FAMILY' => Configuration::get('ASKDIALOG_FONT_FAMILY', ''),
            'ASKDIALOG_HIGHLIGHT_PRODUCT_NAME' => Configuration::get('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME', false),
            'ASKDIALOG_BATCH_SIZE' => Configuration::get('ASKDIALOG_BATCH_SIZE', 1000000)
        ];
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
        } catch (\Exception $e) {
            // Log error but don't break cart functionality
            \PrestaShopLogger::addLog(
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
