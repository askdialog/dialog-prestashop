<?php

namespace LouisAuthie\Askdialog\Service;

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
            throw new \Exception('Le fichier config.yml est introuvable');
        }

        // Parse le fichier YAML
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

        $body = json_encode(['domain' => Context::getContext()->shop->domain]);
        

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