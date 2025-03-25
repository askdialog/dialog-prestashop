<?php

require_once _PS_MODULE_DIR_ . 'askdialog/vendor/autoload.php';

class AskDialogClient
{
    private $apiKey;
    private $urlApi;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->urlApi = $this->getApiUrlFromConfig();
    }

    private function getApiUrlFromConfig()
    {
        $yamlFile = _PS_MODULE_DIR_ . 'askdialog/config/config.yml';
        if (!file_exists($yamlFile)) {
            throw new \Exception('Le fichier config.yml est introuvable');
        }

        // Parse le fichier YAML
        $config = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($yamlFile));

        if (!isset($config['askdialog']['settings']['api_url'])) {
            throw new \Exception('La clé api_url est introuvable dans le fichier config.yml');
        }

        return $config['askdialog']['settings']['api_url'];
    }

    public function sendDomainHost()
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => $this->urlApi,
        ]);

        $headers = [
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $context = \Context::getContext();
        $domain = isset($context->shop) ? $context->shop->domain : '';

        $body = json_encode(['domain' => $domain, 'version' => _PS_VERSION_]);

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
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            return [
                'statusCode' => $statusCode,
                'body' => $e->getMessage(),
            ];
        }
    }

    public function prepareServerTransfer()
    {
        $client = new \GuzzleHttp\Client([
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
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            return [
                'statusCode' => $statusCode,
                'body' => $e->getMessage(),
            ];
        }
    }
}