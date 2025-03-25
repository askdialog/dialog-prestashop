<?php

require_once _PS_MODULE_DIR_ . 'askdialog/src/Service/DataGenerator.php';

class AskDialogApiModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        //Check if token is valid
        
        $headers = getallheaders();
        
        if (substr($headers['Authorization'], 0, 6) !== 'Token ') {
            die(json_encode(["error" => "Public API Token is missing"]));
        }else{
            if($headers['Authorization'] != "Token ".Configuration::get('ASKDIALOG_API_KEY_PUBLIC')){
                die(json_encode(["error" => "Public API Token is wrong"]));
            }
        }
        
        $this->ajax = true;
    }

    public function displayAjax()
    {
        //Get action from the post request in Json
        $action = Tools::getValue('action');
        $dataGenerator = new DataGenerator();

        switch ($action) {
            case 'getCatalogData':
                die(json_encode($dataGenerator->getCatalogData()));
            case 'getLanguageData':
                die(json_encode($dataGenerator->getLanguageData()));
            case 'getProductData':
                $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
                $linkObj = new Link();

                $countryCode = Tools::getValue('country_code');
                $locale = Tools::getValue('locale');
                
                //Si countrycode et locale sont vides, on prend les valeurs par défaut
                if(empty($countryCode) || empty($locale)){
                    $idLang = $defaultLang;
                }else{
                    $idLang = Language::getIdByIso($countryCode);
                    if (!$idLang) {
                        $response = array('status' => 'error', 'message' => 'Invalid country code or locale');
                        die(json_encode($response));
                    }
                }
                die(json_encode($dataGenerator->getProductData(Tools::getValue('id'), $idLang, $linkObj)));
            default:
                $response = array('status' => 'error', 'message' => 'Invalid action');
                die(json_encode($response));
        }
    }
}