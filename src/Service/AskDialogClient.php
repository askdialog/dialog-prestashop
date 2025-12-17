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

namespace Dialog\AskDialog\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Context;
use Symfony\Component\Yaml\Yaml;

class AskDialogClient{
    private $apiKey;
    private $urlApi;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->urlApi = $this->getApiUrlFromConfig();
    }

    private function getApiUrlFromConfig() {
        $yamlFile = _PS_MODULE_DIR_ . 'askdialog/config/config.yml';
        if (!file_exists($yamlFile)) {
            throw new \Exception('Config file config.yml not found');
        }

        // Parse YAML file
        $config = Yaml::parseFile($yamlFile);
        return $config['askdialog']['settings']['api_url'];
    }

    public function sendDomainHost()
    {
        $client = new Client([
            'base_uri' => $this->urlApi,
        ]);

        $headers = [
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $body = json_encode(['domain' => Context::getContext()->shop->domain, 'version' => _PS_VERSION_]);


        try {
            $response = $client->post('/organization/validate', [
                'headers' => $headers,
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            return [
                'statusCode' => $statusCode,
                'body' => $responseBody,
            ];
        } catch (RequestException $e) {
            return [
                'statusCode' => $e->getResponse()->getStatusCode(),
                'body' => $e->getMessage(),
            ];
        }
    }

    public function prepareServerTransfer()
    {
        $client = new Client([
            'base_uri' => $this->urlApi,
        ]);

        $headers = [
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $body = json_encode(['fileType' => 'catalog']);

        try {
            $response = $client->post('/organization/catalog-upload-url', [
                'headers' => $headers,
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            return [
                'statusCode' => $statusCode,
                'body' => $responseBody,
            ];
        } catch (RequestException $e) {
            return [
                'statusCode' => $e->getResponse()->getStatusCode(),
                'body' => $e->getMessage(),
            ];
        }
    }
}
