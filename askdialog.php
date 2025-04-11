<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/src/Service/AskDialogClient.php';
require_once dirname(__FILE__).'/src/Service/DataGenerator.php';

class AskDialog extends Module
{
    public function __construct()
    {
        $this->name = 'askdialog';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'AskDialog';
        $this->need_instance = 0;

        parent::__construct();

        if(_PS_VERSION_ < 1.7){
            $this->bootstrap = true;
            $this->displayName = $this->l('Ask Dialog');
            $this->description = $this->l('Module to provide the AskDialog assistant on your e-shop');
        }
        else {
            $this->bootstrap = true;
            $this->displayName = $this->trans('Ask Dialog', [], 'Modules.AskDialog.Admin');
            $this->description = $this->trans('Module to provide the AskDialog assistant on your e-shop', [], 'Modules.AskDialog.Admin');
        }
        
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        
    }

    private function createTables()
    {
        //Create table to store all the products remaining to add to JSON file before sending it to AskDialog S3 server as a batch
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'askdialog_product` (
            `id_askdialog_product` int(11) NOT NULL AUTO_INCREMENT,
            `id_product` int(11) NOT NULL,
            `id_shop` int(11) NOT NULL,
            `date_add` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_askdialog_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
        return Db::getInstance()->execute($sql);
    }

    private function dropTables()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'askdialog_product`';
        return Db::getInstance()->execute($sql);
    }

    public function install()
    {
        return parent::install() 
            && $this->registerHook('displayHeader') 
            && $this->registerHook('displayFooter') 
            && $this->registerHook('displayFooterProduct') 
            && $this->registerHook('actionFrontControllerInitBefore') 
            && $this->createTables()
            && $this->setDefaultConfigurationValues();
    }

    private function setDefaultConfigurationValues()
    {
        return Configuration::updateValue('ASKDIALOG_COLOR_PRIMARY', '#CCCCCC') // Exemple de couleur par défaut
            && Configuration::updateValue('ASKDIALOG_COLOR_BACKGROUND', '#FFFFFF') // Exemple de couleur de fond par défaut
            && Configuration::updateValue('ASKDIALOG_COLOR_CTA_TEXT', '#000000') // Exemple de couleur de texte CTA par défaut
            && Configuration::updateValue('ASKDIALOG_CTA_BORDER_TYPE', 'solid') // Exemple de type de bordure par défaut
            && Configuration::updateValue('ASKDIALOG_CAPITALIZE_CTAS',0) // Valeur booléenne par défaut
            && Configuration::updateValue('ASKDIALOG_FONT_FAMILY', 'Arial, sans-serif') // Exemple de police par défaut
            && Configuration::updateValue('ASKDIALOG_HIGHLIGHT_PRODUCT_NAME', 0) // Valeur booléenne par défaut
            && Configuration::updateValue('ASKDIALOG_BATCH_SIZE', 1000000); // Taille de lot par défaut
    }

    public function uninstall()
    {
        return parent::uninstall() && $this->dropTables();
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


    public function hookDisplayHeader($params)
    {
        if ($this->context->controller->php_self == 'product') {
            $this->context->controller->addCSS($this->_path . 'views/css/cssForProductPage.css', 'all');
        }
        $this->context->controller->addCSS($this->_path . 'views/css/cssForAllPages.css', 'all');

        //$this->context->controller->addJS($this->_path . 'views/js/index.js');
        //Add JS
        $this->context->controller->addJS($this->_path . 'views/js/setupModal.js');

        //Si page produit
        if ($this->context->controller->php_self == 'product') {
            $this->context->controller->addJS($this->_path . 'views/js/instant.js');
        }
        else {
            $this->context->controller->addJS($this->_path . 'views/js/ai-input.js');
        }

        $this->context->controller->addJS($this->_path . 'views/js/askdialog.js');
       
    }

    public function hookDisplayFooterProduct($params)
    {
        $enableProductHook = Configuration::get('ASKDIALOG_ENABLE_PRODUCT_HOOK') ? 1 : 0;
        if (!$enableProductHook) {
            return;
        }

        $product = $params['product'];
        $this->context->smarty->assign('product', $product);

        $product_id = $product->id;
        $product_title = $product->name;
        $product_slug = $product->link_rewrite;
    
        $selected_variant_id = 0;

        if (_PS_VERSION_ < 1.7) {
            $assistant_name = $this->l('AskDialog Assistant');
            $assistant_description = $this->l('How can I help you with this product?');
            $ask_anything_placeholder = $this->l('Ask anything about this product...');
        } else {
            $assistant_name = $this->trans('AskDialog Assistant', [], 'Modules.AskDialog.Admin');
            $assistant_description = $this->trans('How can I help you with this product?', [], 'Modules.AskDialog.Admin');
            $ask_anything_placeholder = $this->trans('Ask anything about this product...', [], 'Modules.AskDialog.Admin');
        }

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
            'my_array' => ['suggestion-0', 'suggestion-1']
        ]);

        return $this->display(__FILE__, 'views/templates/hook/displayproduct.tpl');
    }

    public function hookFooter($params)
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

        if (_PS_VERSION_ < 1.7) {
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
        } else {
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
        }

        return $this->display(__FILE__, 'views/templates/hook/displayfooterafter.tpl');
    }

    public function getContent()
    {
        $output = '';
        
        // If we are at step 1 or no step is defined, show the API Keys configuration form
        if (Tools::getValue('test') == null && (Tools::getValue('step') == 1 || Tools::getValue('step') == null)) {
            if (Tools::isSubmit('submitStep1' . $this->name)) {
            $apiKey = strval(Tools::getValue('ASKDIALOG_API_KEY'));
            $apiKeyPublic = strval(Tools::getValue('ASKDIALOG_API_KEY_PUBLIC'));
            $enableProductHook = (bool)Tools::getValue('ASKDIALOG_ENABLE_PRODUCT_HOOK');
            var_dump("Debug");
            var_dump($enableProductHook);
            if (!$apiKey || empty($apiKey)) {
                $output .= $this->displayError($this->l('Invalid API Key'));
            } else {
                Configuration::updateValue('ASKDIALOG_API_KEY', $apiKey);
                Configuration::updateValue('ASKDIALOG_API_KEY_PUBLIC', $apiKeyPublic);
                Configuration::updateValue('ASKDIALOG_ENABLE_PRODUCT_HOOK', $enableProductHook);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
                $apiClient = new AskDialogClient($apiKey);
                $result = $apiClient->sendDomainHost();
                if ($result) {
                $output .= $this->displayConfirmation($this->l('Connection successful'));
                } else {
                $output .= $this->displayError($this->l('Connection failed'));
                }
            }
            }
            return $output . $this->renderFormApiKeys();
        } else if (Tools::getValue('step') == 2 ) {

            if (Tools::isSubmit('submitStep2' . $this->name)) {
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
                $output .= $this->displayConfirmation($this->l('Settings updated'));
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
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('API Key public'),
                        'name' => 'ASKDIALOG_API_KEY_PUBLIC',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('API Key private'),
                        'name' => 'ASKDIALOG_API_KEY',
                        'size' => 20,
                        'required' => true,
                    ],
                    // Add a toggle switch to enable or disable the hook on the product page
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable on Product Page'),
                        'name' => 'ASKDIALOG_ENABLE_PRODUCT_HOOK',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ],
                        'desc' => $this->l('Enable or disable the AskDialog assistant on the product page.')
                    ],
                    // Add a link to the AskDialog website
                    [
                        'type' => 'html',
                        'name' => 'askdialog_link',
                        'html_content' => '<a href="https://app.askdialog.com/onboarding" class="btn btn-info" target="_blank">Go to AskDialog Onboarding</a>'
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'name' => 'submitStep1' . $this->name
                ],
                'buttons' => [
                    [
                        'href' => $this->context->link->getAdminLink('AdminModules', false)
                            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name.'&step=2&token='.Tools::getAdminTokenLite('AdminModules'),
                        'title' => $this->l('Next'),
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
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'color',
                        'label' => $this->l('Primary Color'),
                        'name' => 'ASKDIALOG_COLOR_PRIMARY',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Background Color'),
                        'name' => 'ASKDIALOG_COLOR_BACKGROUND',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('CTA Text Color'),
                        'name' => 'ASKDIALOG_COLOR_CTA_TEXT',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('CTA Border Type'),
                        'name' => 'ASKDIALOG_CTA_BORDER_TYPE',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Capitalize CTAs'),
                        'name' => 'ASKDIALOG_CAPITALIZE_CTAS',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Font Family'),
                        'name' => 'ASKDIALOG_FONT_FAMILY',
                        'size' => 20,
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Highlight Product Name'),
                        'name' => 'ASKDIALOG_HIGHLIGHT_PRODUCT_NAME',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled')
                            ]
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Batch Size'),
                        'name' => 'ASKDIALOG_BATCH_SIZE',
                        'size' => 20,
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'name' => 'submitStep2' . $this->name
                ],
                'buttons' => [
                    [
                        'href' => $this->context->link->getAdminLink('AdminModules', false)
                            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name.'&step=1&token='.Tools::getAdminTokenLite('AdminModules'),
                        'title' => $this->l('Previous'),
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
}