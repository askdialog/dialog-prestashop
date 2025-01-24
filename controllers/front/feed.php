<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use LouisAuthie\Askdialog\Service\DataGenerator;
use LouisAuthie\Askdialog\Service\AskDialogClient;

class AskDialogFeedModuleFrontController extends ModuleFrontController
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
            case 'sendCatalogData':
                $dataCatalog = $dataGenerator->getCatalogData();

                $askDialogClient = new AskDialogClient(Configuration::get('ASKDIALOG_API_KEY'));
                $return = $askDialogClient->prepareServerTransfer();
                $bodyPrepared = json_decode($return['body'], true);

                $url = $bodyPrepared['url'];
                $fields = $bodyPrepared['fields'];

                //send a PUT request to presigned  aws URL in a  request with guzzle client adding the fields $fields to url in GET the request
                $client = new Client(['verify' => false]);
    
                $filename = 'catalog_' . date('Ymd_His') . '.json';
                // Generate a temporary file to store the JSON data
                $tempFile = _PS_MODULE_DIR_ . 'askdialog/temp/'.$filename;
                file_put_contents($tempFile, json_encode($dataCatalog));

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
                    'contents' => 'text/csv'
                ];

            try {
                echo "<pre>";
                echo "Policy :\n";
                echo base64_decode($fields['Policy']);
                echo "\n";
                echo "Request Headers:\n";
                print_r($data);
                echo "</pre>";
                $response = $client->post($url, $data);
            } catch (RequestException $e) {
                echo "<pre>";
                
                if ($e->hasResponse()) {
                    echo "Response Body:\n";
                    echo $e->getResponse()->getBody()->getContents();
                }
                echo "</pre>";
            }

                break;
                
            default:
                $response = array('status' => 'error', 'message' => 'Invalid action');
                die(json_encode($response));
        }
    }
}