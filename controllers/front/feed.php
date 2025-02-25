<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use LouisAuthie\Askdialog\Service\DataGenerator;
use LouisAuthie\Askdialog\Service\AskDialogClient;
use PSpell\Config;

class AskDialogFeedModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        //Check if token is valid
        $headers = getallheaders();
        
        if (substr($headers['Authorization'], 0, 6) !== 'Token ') {
            die(json_encode(["error" => "Privte API Token is missing"]));
        }else{
            if($headers['Authorization'] != "Token ".Configuration::get('ASKDIALOG_API_KEY')){
                die(json_encode(["error" => "Private API Token is wrong"]));
            }
        }
        $this->ajax = true;
    }

    public function sendFileToUrl($url, $fields, $tempFile, $filename, $client) {
        $data = ['multipart' => array_merge(
            array_map(function($name, $contents) {                
                return [
                    'name'     => $name,
                    'contents' => $contents,
                ];
            }, array_keys($fields), $fields),
            [
                [
                    'name'     => 'file',
                    'contents' => fopen($tempFile, 'r'),
                    'filename' => $filename,
                ]
            ]
        )];
        $data['multipart'][] = [
            'name'=> 'Content-Type',
            'contents' => 'application/json'
        ];

        return $client->post($url, $data);
    }

    public function displayAjax()
    {
        //Get action from the post request in Json
        $action = Tools::getValue('action');
        $dataGenerator = new DataGenerator();
        $batchSize = Configuration::get('ASKDIALOG_BATCH_SIZE');

        switch ($action) {
            case 'sendCatalogData':
                $numRemaining = $dataGenerator->getNumCatalogRemaining(Configuration::get('PS_SHOP_DEFAULT'));
                $dataCatalog = $dataGenerator->getCatalogDataForBatch($batchSize, Configuration::get('PS_SHOP_DEFAULT'));

                if($numRemaining>0 && $numRemaining <= $batchSize){
                    $this->generatePartialDataFile($dataCatalog);
                    $numRemaining = $dataGenerator->getNumCatalogRemaining(Configuration::get('PS_SHOP_DEFAULT'));
                }

                if ($numRemaining == 0) {
                    //Check if there are files in the temp folder
                    $files = glob(_PS_MODULE_DIR_ . 'askdialog/temp/catalog_partial_*.json');
                    //if there are files in this temp folder, load their content in a loop and check the JSON validity of each, then merge them all in one file
                    if (!empty($files)) {
                        $dataCatalog = [];
                        foreach ($files as $file) {
                            $dataCatalog = array_merge($dataCatalog, json_decode(file_get_contents($file), true));
                        }
                        //Delete all files in the temp folder
                        array_map('unlink', glob(_PS_MODULE_DIR_ . 'askdialog/temp/*'));

                        $filename = 'catalog_' . date('Ymd_His') . '.json';
                        // Generate a temporary file to store the JSON data
                        $tempFile = _PS_MODULE_DIR_ . 'askdialog/temp/'.$filename;
                        file_put_contents($tempFile, json_encode($dataCatalog));
                    }


                    //Go for a new batch
                    //Add all the products to generate to the database
                    $sql = 'TRUNCATE TABLE ' . _DB_PREFIX_ . 'askdialog_product';
                    Db::getInstance()->execute($sql);

                    $products = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'product');
                    foreach ($products as $product) {
                        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'askdialog_product (id_product, id_shop) VALUES (' . $product['id_product'] . ', ' . Configuration::get('PS_SHOP_DEFAULT') . ')';
                        Db::getInstance()->execute($sql);
                    }
                    
                    if (empty($files)) {
                        break;
                    }
                    
                    
                }
                else{
                    //Prepare a partial file
                    $this->generatePartialDataFile($dataCatalog);
                    break;
                }

                //Generate cms pages export 
                $dataGenerator->generateCMSData();
                

                //Prepare the server transfer
                $askDialogClient = new AskDialogClient(Configuration::get('ASKDIALOG_API_KEY'));
                $return = $askDialogClient->prepareServerTransfer();
                $bodyPrepared = json_decode($return['body'], true);

                $bodyCatalog = $bodyPrepared['catalogUploadUrl'];
                $bodyPages = $bodyPrepared['pageUploadUrl'];
                $bodyBlogPost = $bodyPrepared['blogPostUploadUrl'];
    

                
                $client = new Client(['verify' => false]);
                
                try {
                    // Send to Catalog URL
                    $urlCatalog = $bodyCatalog['url'];
                    $fieldsCatalog = $bodyCatalog['fields'];
                    $responseCatalog = $this->sendFileToUrl($urlCatalog, $fieldsCatalog, $tempFile, $filename, $client);
                    
                    // Send to Pages URL
                    $urlPages = $bodyPages['url'];
                    $fieldsPages = $bodyPages['fields'];
                    $tempsCmsFile = _PS_MODULE_DIR_ . 'askdialog/temp/cms.json';
                    $responsePages = $this->sendFileToUrl($urlPages, $fieldsPages, $tempsCmsFile, 'cms.json', $client);

                    // Check both responses
                    if ($responseCatalog->getStatusCode() == 204 && $responsePages->getStatusCode() == 204) {
                    //if ($responseCatalog->getStatusCode() == 204) {
                        rename($tempFile, _PS_MODULE_DIR_ . 'askdialog/sent/' . $filename);
                        $filenameCMS = 'cms_' . date('Ymd_His') . '.json';
                        rename($tempsCmsFile, _PS_MODULE_DIR_ . 'askdialog/sent/'. $filenameCMS);
                        $response = array('status' => 'success', 'message' => 'Catalog and Pages data sent successfully');
                        die(json_encode($response));
                    } else {
                        $response = array('status' => 'error', 'message' => 'Error sending data');
                        die(json_encode($response));
                    }

                } catch (RequestException $e) {
                    echo "<pre>";
                    if ($e->hasResponse()) {
                        echo "Response Body:\n";
                        echo $e->getResponse()->getBody()->getContents();
                    }
                    echo "</pre>";
                    
                    $response = array('status' => 'error', 'message' => 'Exception while sending data: ' . $e->getMessage());
                    die(json_encode($response));
                }

                break;
                
            default:
                $response = array('status' => 'error', 'message' => 'Invalid action');
                die(json_encode($response));
        }
    }

    private function generatePartialDataFile($dataCatalog)
    {
        $filename = 'catalog_partial_' . date('Ymd_His') . '.json';
        // Generate a temporary file to store the JSON data
        $tempFile = _PS_MODULE_DIR_ . 'askdialog/temp/' . $filename;
        file_put_contents($tempFile, json_encode($dataCatalog));

        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'askdialog_product WHERE id_shop = ' . Configuration::get('PS_SHOP_DEFAULT') . ' AND id_product IN (' . implode(',', array_column($dataCatalog, 'id')) . ')';
        Db::getInstance()->execute($sql);

        $dataGenerator = new DataGenerator();
        if($dataGenerator->getNumCatalogRemaining(Configuration::get('PS_SHOP_DEFAULT'))>0){
            die(json_encode(array('status' => 'success', 'message' => 'Partial data generated')));
        }
    }
}