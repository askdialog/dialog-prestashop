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

namespace Dialog\AskDialog\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\Logger;
use Dialog\AskDialog\Service\Http\HttpTransport;

/**
 * PostHog Analytics Service
 *
 * Handles server-side event tracking to PostHog using their HTTP API.
 *
 * Events are only buffered while the request runs and leave in one batch from
 * a shutdown function: a blocking POST per event cost a shopper's cart about
 * 300 ms per product on a store rebuilding a whole cart server-side.
 *
 * @see https://posthog.com/docs/api/capture
 */
class PostHogService
{
    /**
     * PostHog API endpoint for EU region
     */
    private const API_ENDPOINT = 'https://eu.i.posthog.com';

    /**
     * PostHog project API key
     */
    private const API_KEY = 'phc_EKMR6Jt4OTMEYmoUlz0v58KPwqcFxI7aZCLckpSD8Tv';

    /**
     * Longest a batch may take once the client has its response, in seconds
     */
    private const MAX_DURATION_AFTER_RESPONSE = 3.0;

    /**
     * Longest a batch may take where the SAPI cannot end the response early
     * (mod_php, CLI), in seconds: the client is still waiting on this request
     */
    private const MAX_DURATION_BEFORE_RESPONSE = 0.5;

    /**
     * @var array Events buffered until the end of the request
     */
    private static $pendingEvents = [];

    /**
     * @var bool Whether the shutdown function is registered for this request
     */
    private static $shutdownRegistered = false;

    /**
     * Capture an event to PostHog
     *
     * @param string $event Event name (e.g., 'user_added_to_cart', 'Order Confirmation')
     * @param array $properties Event properties
     * @param string|null $distinctId Unique identifier for the user (if null, will be generated)
     *
     * @return bool True once the event is buffered for the end of the request
     */
    public function capture($event, array $properties = [], $distinctId = null)
    {
        // Generate distinct ID if not provided
        if ($distinctId === null) {
            $distinctId = $this->getDistinctId();
        }

        // Timestamped here: PostHog would otherwise date it at the batch's arrival
        self::$pendingEvents[] = [
            'event' => $event,
            'distinct_id' => $distinctId,
            'properties' => $this->buildEventProperties($properties),
            'timestamp' => self::formatNow(),
        ];

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'scheduleFlush']);
        }

        return true;
    }

    /**
     * Move the flush behind every other shutdown function
     *
     * Ending the response drops anything sent afterwards, so a shutdown
     * function registered after this one must still get to write its output.
     *
     * @return void
     */
    public static function scheduleFlush()
    {
        register_shutdown_function([self::class, 'flushPendingEvents']);
    }

    /**
     * Send the buffered events to PostHog as one batch
     *
     * Runs in a shutdown function, where an uncaught failure would land on the
     * page, so this catches Throwable and only ever logs.
     *
     * @return void
     */
    public static function flushPendingEvents()
    {
        $batch = self::$pendingEvents;
        self::$pendingEvents = [];

        if (empty($batch)) {
            return;
        }

        try {
            $responseEnded = self::endResponse();

            $httpClient = new HttpTransport([
                'base_uri' => self::API_ENDPOINT,
                'max_duration' => $responseEnded
                    ? self::MAX_DURATION_AFTER_RESPONSE
                    : self::MAX_DURATION_BEFORE_RESPONSE,
            ]);
            $response = $httpClient->postJson('/batch/', [
                'api_key' => self::API_KEY,
                'sent_at' => self::formatNow(), // Lets PostHog correct the shop's clock drift
                'batch' => $batch,
            ]);

            if ($response->getStatusCode() !== 200) {
                Logger::log('PostHog API HTTP error', 3, $response->getStatusCode(), 'PostHogService');
            }
        } catch (\Throwable $e) {
            Logger::log('PostHog API transport error: ' . $e->getMessage(), 3, null, 'PostHogService');
        }
    }

    /**
     * Hand the client its response, where the SAPI can do that before the script ends
     *
     * The cookie must go first: PrestaShop otherwise writes it from
     * Cookie::__destruct, which runs after shutdown functions, so the headers
     * of a first add-to-cart would leave without the new cart's id.
     *
     * @return bool True if the client is no longer waiting on this request
     */
    private static function endResponse()
    {
        $context = \Context::getContext();
        if (isset($context->cookie)) {
            $context->cookie->write();
        }

        if (function_exists('fastcgi_finish_request')) {
            return fastcgi_finish_request();
        }

        if (function_exists('litespeed_finish_request')) {
            return litespeed_finish_request();
        }

        return false;
    }

    /**
     * Current time as PostHog expects it, to the millisecond: attribution
     * compares these events with the browser's
     *
     * @return string
     */
    private static function formatNow()
    {
        return \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)))->format('Y-m-d\\TH:i:s.v\\Z');
    }

    /**
     * Parse the PostHog frontend cookie
     *
     * @return array|null Parsed cookie data or null if not available
     */
    private function parsePostHogCookie()
    {
        $cookieName = 'ph_' . self::API_KEY . '_posthog';

        Logger::log(
            'PostHog cookie check - Cookie name: ' . $cookieName . ' | Available cookies: ' . implode(', ', array_keys($_COOKIE)),
            1,
            null,
            'PostHogService'
        );

        if (!isset($_COOKIE[$cookieName])) {
            return null;
        }

        $posthogData = json_decode($_COOKIE[$cookieName], true);

        return is_array($posthogData) ? $posthogData : null;
    }

    /**
     * Generate a stable distinct_id for the current user
     *
     * Priority:
     * 1. PostHog frontend distinct_id (from cookie) - ensures continuity with JS SDK
     * 2. Customer ID (if logged in)
     * 3. Cart ID (if guest with cart)
     * 4. Session ID (fallback)
     *
     * @param \Customer|null $customer
     * @param \Cart|null $cart
     *
     * @return string
     */
    public function getDistinctId($customer = null, $cart = null)
    {
        $cookieData = $this->parsePostHogCookie();
        if ($cookieData !== null && isset($cookieData['distinct_id']) && !empty($cookieData['distinct_id'])) {
            return $cookieData['distinct_id'];
        }

        // Fallback logic when PostHog cookie is not available
        $context = \Context::getContext();

        // Use provided customer or get from context
        if ($customer === null && isset($context->customer) && \Validate::isLoadedObject($context->customer)) {
            $customer = $context->customer;
        }

        // Use provided cart or get from context
        if ($cart === null && isset($context->cart) && \Validate::isLoadedObject($context->cart)) {
            $cart = $context->cart;
        }

        // Priority 2: Customer ID (logged in users)
        if ($customer && $customer->id) {
            return 'customer_' . $customer->id;
        }

        // Priority 3: Cart ID (guest users with cart)
        if ($cart && $cart->id) {
            return 'cart_' . $cart->id;
        }

        // Priority 4: Session ID (fallback)
        if (session_id()) {
            return 'session_' . session_id();
        }

        // Ultimate fallback: generate unique ID
        return 'anonymous_' . uniqid();
    }

    /**
     * Get the PostHog session ID from the frontend cookie
     *
     * The cookie stores session info in the $sesid field as [timestamp, sessionId, startTimestamp].
     *
     * @return string|null Session ID or null if not available
     */
    public function getSessionId()
    {
        $cookieData = $this->parsePostHogCookie();
        if ($cookieData === null || !isset($cookieData['$sesid'][1])) {
            return null;
        }

        return $cookieData['$sesid'][1];
    }

    /**
     * Build event properties with required PostHog settings
     *
     * IMPORTANT: Always sets $process_person_profile to false to avoid
     * creating full user profiles (GDPR compliance + performance)
     *
     * @param array $properties User-defined properties
     *
     * @return array Complete properties array
     */
    private function buildEventProperties(array $properties)
    {
        $context = \Context::getContext();

        // $host: shop domain
        $host = '';
        if (isset($context->shop) && !empty($context->shop->domain)) {
            $host = $context->shop->domain;
        } elseif (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        }

        // $pathname: extract from referer (original page where user triggered the action)
        // Note: URL fragment (#...) is never sent to server, but variantId is tracked separately
        $pathname = '';
        if (isset($_SERVER['HTTP_REFERER'])) {
            $pathname = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
        }

        // $session_id: PostHog session ID from frontend cookie for cross-page journey linking
        $sessionId = $this->getSessionId();

        $requiredProperties = [
            '$process_person_profile' => false,
            '$host' => $host,
            '$pathname' => $pathname,
        ];

        if ($sessionId !== null) {
            $requiredProperties['$session_id'] = $sessionId;
        }

        // Merge user properties with required PostHog settings
        return array_merge($properties, $requiredProperties);
    }

    /**
     * Track add to cart event
     *
     * Only tracks positive quantity increments (not decrements/removals)
     *
     * @param int $idProduct Product ID
     * @param int $idProductAttribute Combination ID (0 if none)
     * @param int $quantity Quantity added (must be positive)
     * @param \Cart $cart Cart object
     *
     * @return bool True once the event is buffered, false if it does not qualify
     */
    public function trackAddToCart($idProduct, $idProductAttribute, $quantity, $cart)
    {
        // Safety check: should never happen as hook filters operator='up' only
        // Kept as defensive programming in case method is called directly
        if ($quantity <= 0) {
            return false;
        }

        $context = \Context::getContext();
        $distinctId = $this->getDistinctId(null, $cart);

        $properties = [
            'productId' => $idProduct,
            'quantity' => $quantity,
            'currency' => isset($context->currency)
                ? $context->currency->iso_code
                : 'EUR',
        ];

        // Add variant ID if it's a combination
        if ($idProductAttribute > 0) {
            $properties['variantId'] = $idProductAttribute;
        }

        return $this->capture('user_added_to_cart', $properties, $distinctId);
    }

    /**
     * Track order confirmation event
     *
     * @param \Order $order Order object
     *
     * @return bool
     */
    public function trackOrderConfirmation($order)
    {
        $customer = new \Customer($order->id_customer);
        $distinctId = $this->getDistinctId($customer);

        $currency = $order->id_currency ? (new \Currency($order->id_currency))->iso_code : 'EUR';
        $orderProducts = $order->getProducts();

        $items = [];
        foreach ($orderProducts as $product) {
            $item = [
                'currency' => $currency,
                'price' => (float) $product['product_price_wt'],
                'productId' => (string) $product['product_id'],
                'productTitle' => $product['product_name'],
                'quantity' => (int) $product['product_quantity'],
            ];

            if (!empty($product['product_attribute_id'])) {
                $item['variantId'] = (string) $product['product_attribute_id'];
            }

            $items[] = $item;
        }

        $properties = [
            'items' => $items,
            'orderValue' => (float) $order->total_paid,
            'currency' => $currency,
            'order_reference' => $order->reference,
        ];

        return $this->capture('user_submitted_checkout', $properties, $distinctId);
    }
}
