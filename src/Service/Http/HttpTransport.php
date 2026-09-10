<?php
/**
 * 2026 Dialog
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
 * @author    Axel Paillaud <contact@axelweb.fr>
 * @copyright 2026 Dialog
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Dialog\AskDialog\Service\Http;

/**
 * HTTP transport with two interchangeable backends.
 *
 * Symfony's HttpClient and Mime components only ship with PrestaShop 8.0+
 * (Symfony 4.4); 1.7.x bundles Symfony 3.4, where they simply do not exist.
 * Referencing them there raises `Class not found` — a *fatal*, not an
 * exception, so it escapes the module's try/catch and takes the whole page
 * down (seen live on a 1.7.x store: fatal on every add-to-cart).
 *
 * This class picks Symfony when present and falls back to cURL — required by
 * PrestaShop on every version — otherwise. Both backends return the same
 * HttpResponse and only ever throw HttpTransportException, so callers are
 * identical on both paths.
 */
class HttpTransport
{
    public const SYMFONY_HTTP_CLIENT = 'Symfony\Component\HttpClient\HttpClient';
    public const SYMFONY_DATA_PART = 'Symfony\Component\Mime\Part\DataPart';
    public const SYMFONY_FORM_DATA_PART = 'Symfony\Component\Mime\Part\Multipart\FormDataPart';

    public const DEFAULT_TIMEOUT = 30;

    /**
     * A transfer moving slower than this for `timeout` seconds counts as idle.
     * One byte per second only trips on a genuinely stalled connection.
     */
    public const MIN_BYTES_PER_SECOND = 1;

    /** @var string */
    private $baseUri;

    /** @var array<string, string> */
    private $headers;

    /** @var int */
    private $timeout;

    /**
     * @param array $options base_uri, headers, timeout
     */
    public function __construct(array $options = [])
    {
        $this->baseUri = isset($options['base_uri']) ? (string) $options['base_uri'] : '';
        $this->headers = isset($options['headers']) && is_array($options['headers'])
            ? $options['headers']
            : [];
        $this->timeout = isset($options['timeout']) ? (int) $options['timeout'] : self::DEFAULT_TIMEOUT;
    }

    /**
     * True when the Symfony HTTP stack is usable (PrestaShop 8.0+).
     *
     * @return bool
     */
    public static function hasSymfonyHttpClient()
    {
        return class_exists(self::SYMFONY_HTTP_CLIENT);
    }

    /**
     * True when the Symfony Mime parts needed for multipart uploads exist.
     *
     * @return bool
     */
    public static function hasSymfonyMime()
    {
        return class_exists(self::SYMFONY_DATA_PART) && class_exists(self::SYMFONY_FORM_DATA_PART);
    }

    /**
     * POST a JSON payload.
     *
     * @param string $path Absolute URL, or path resolved against base_uri
     * @param array $payload
     *
     * @return HttpResponse
     *
     * @throws HttpTransportException On transport failure only
     */
    public function postJson($path, array $payload)
    {
        $url = $this->resolveUrl($path);
        $body = json_encode($payload);

        if ($body === false) {
            throw new HttpTransportException('Unable to encode the request payload as JSON');
        }

        if (self::hasSymfonyHttpClient()) {
            return $this->symfonyPost($url, $body, $this->headersWith('Content-Type', 'application/json'));
        }

        return $this->curlPost($url, $body, $this->headersWith('Content-Type', 'application/json'));
    }

    /**
     * POST a multipart form carrying one file.
     *
     * Field order is preserved on both backends: S3 POST policies require the
     * `file` field to come last, and the caller builds the array that way.
     *
     * @param string $path Absolute URL, or path resolved against base_uri
     * @param array $fields Scalar form fields, in order
     * @param string $filePath Absolute path of the file to send
     * @param string $filename Name announced to the server
     * @param string $fileContentType
     *
     * @return HttpResponse
     *
     * @throws HttpTransportException On transport failure only
     */
    public function postMultipart($path, array $fields, $filePath, $filename, $fileContentType)
    {
        if (!is_file($filePath)) {
            throw new HttpTransportException('File not found: ' . $filePath);
        }

        $url = $this->resolveUrl($path);

        if (self::hasSymfonyHttpClient() && self::hasSymfonyMime()) {
            return $this->symfonyPostMultipart($url, $fields, $filePath, $filename, $fileContentType);
        }

        return $this->curlPostMultipart($url, $fields, $filePath, $filename, $fileContentType);
    }

    /**
     * @param string $url
     * @param string $body
     * @param array $headers
     *
     * @return HttpResponse
     */
    private function symfonyPost($url, $body, array $headers)
    {
        $factory = self::SYMFONY_HTTP_CLIENT;
        $client = $factory::create();

        try {
            $response = $client->request('POST', $url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $this->timeout,
            ]);

            // `false` keeps 4xx/5xx out of the exception path: the status is
            // part of the answer here, never a transport failure.
            return new HttpResponse($response->getStatusCode(), $response->getContent(false));
        } catch (\Throwable $exception) {
            throw new HttpTransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param string $url
     * @param array $fields
     * @param string $filePath
     * @param string $filename
     * @param string $fileContentType
     *
     * @return HttpResponse
     */
    private function symfonyPostMultipart($url, array $fields, $filePath, $filename, $fileContentType)
    {
        $factory = self::SYMFONY_HTTP_CLIENT;
        $dataPart = self::SYMFONY_DATA_PART;
        $formDataPart = self::SYMFONY_FORM_DATA_PART;

        try {
            $fields['file'] = $dataPart::fromPath($filePath, $filename, $fileContentType);
            $formData = new $formDataPart($fields);

            $client = $factory::create();
            $response = $client->request('POST', $url, [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToString(),
                'timeout' => $this->timeout,
            ]);

            return new HttpResponse($response->getStatusCode(), $response->getContent(false));
        } catch (\Throwable $exception) {
            throw new HttpTransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param string $url
     * @param string $body
     * @param array $headers
     *
     * @return HttpResponse
     */
    private function curlPost($url, $body, array $headers)
    {
        return $this->curlExecute($url, $body, $this->formatCurlHeaders($headers));
    }

    /**
     * @param string $url
     * @param array $fields
     * @param string $filePath
     * @param string $filename
     * @param string $fileContentType
     *
     * @return HttpResponse
     */
    private function curlPostMultipart($url, array $fields, $filePath, $filename, $fileContentType)
    {
        // cURL builds the multipart body itself from the array, keeping its
        // order, and sets the boundary — so no Content-Type header is passed
        // here: forcing one would drop the boundary and break the upload.
        $fields['file'] = new \CURLFile($filePath, $fileContentType, $filename);

        $headers = $this->headers;
        unset($headers['Content-Type'], $headers['content-type']);

        return $this->curlExecute($url, $fields, $this->formatCurlHeaders($headers));
    }

    /**
     * @param string $url
     * @param string|array $body
     * @param array<int, string> $headers
     *
     * @return HttpResponse
     */
    private function curlExecute($url, $body, array $headers)
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new HttpTransportException('Unable to initialize cURL for ' . $url);
        }

        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);

        // Mirror Symfony's semantics: `timeout` is an IDLE timeout, and the
        // overall duration is unlimited (`max_duration` defaults to 0). Using
        // CURLOPT_TIMEOUT here would instead cap the whole transfer, failing a
        // large catalogue upload that is still progressing.
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_LOW_SPEED_LIMIT, self::MIN_BYTES_PER_SECOND);
        curl_setopt($handle, CURLOPT_LOW_SPEED_TIME, $this->timeout);

        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

        if (!empty($headers)) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        }

        $content = curl_exec($handle);
        $errorMessage = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($content === false) {
            throw new HttpTransportException(
                'cURL request to ' . $url . ' failed: ' . ($errorMessage !== '' ? $errorMessage : 'unknown error')
            );
        }

        return new HttpResponse($statusCode, (string) $content);
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return array<string, string>
     */
    private function headersWith($name, $value)
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<int, string>
     */
    private function formatCurlHeaders(array $headers)
    {
        $formatted = [];
        foreach ($headers as $name => $value) {
            $formatted[] = $name . ': ' . $value;
        }

        return $formatted;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function resolveUrl($path)
    {
        $path = (string) $path;

        if ($this->baseUri === '' || preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return rtrim($this->baseUri, '/') . '/' . ltrim($path, '/');
    }
}
