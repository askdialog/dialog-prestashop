<?php

use LouisAuthie\Askdialog\Service\DataGenerator;

class AskDialogApiModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        //Check if token is valid
        $token = Tools::getValue('token');
        if ($token != Configuration::get('ASKDIALOG_API_KEY')) {
            $response = array('status' => 'error', 'message' => 'Invalid token');
            die(json_encode($response));
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
            case 'getProductData':
                $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
                $linkObj = new Link();

                $countryCode = Tools::getValue('country_code');
                $locale = Tools::getValue('locale');

                //Si countrycode et locale sont vides, on prend les valeurs par défaut
                if(empty($countryCode) || empty($locale)){
                    $idLang = $defaultLang;
                }else{
                    $idLang = Language::getIdByLocale($countryCode . '-' . $locale);
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