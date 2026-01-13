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

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PostHog Analytics Service
 *
 * Handles server-side event tracking to PostHog using their HTTP API.
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
    private const API_KEY = 'phc_WM5MRkqG7AiqOKeTmNKj0fNIl41ZOQex7wRhEswRlTA';

    /**
     * @var HttpClientInterface Symfony HTTP client instance
     */
    private $httpClient;

    /**
     * PostHogService constructor
     *
     * Initializes Symfony HttpClient with PostHog configuration
     */
    public function __construct()
    {
        $this->httpClient = HttpClient::create([
            'base_uri' => self::API_ENDPOINT,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 5, // 5 second timeout to avoid blocking requests
        ]);
    }

    /**
     * Capture an event to PostHog
     *
     * @param string $event Event name (e.g., 'user_added_to_cart', 'Order Confirmation')
     * @param array $properties Event properties
     * @param string|null $distinctId Unique identifier for the user (if null, will be generated)
     * @return bool True if event was sent successfully, false otherwise
     */
    public function capture($event, array $properties = [], $distinctId = null)
    {
        // Generate distinct ID if not provided
        if ($distinctId === null) {
            $distinctId = $this->getDistinctId();
        }

        // Build event payload
        $payload = [
            'api_key' => self::API_KEY,
            'event' => $event,
            'distinct_id' => $distinctId,
            'properties' => $this->buildEventProperties($properties),
        ];

        // Send to PostHog API
        return $this->sendToPostHog($payload);
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
     * @return string
     */
    public function getDistinctId($customer = null, $cart = null)
    {
        // Priority 1: Use PostHog's frontend distinct_id from cookie
        // This ensures backend events are linked to the same user as frontend events
        $cookieName = 'ph_' . self::API_KEY . '_posthog';

        if (isset($_COOKIE[$cookieName])) {
            try {
                $posthogData = json_decode($_COOKIE[$cookieName], true);
                dump($posthogData);
                die();
                if (isset($posthogData['distinct_id']) && !empty($posthogData['distinct_id'])) {
                    return $posthogData['distinct_id'];
                }
            } catch (\Exception $e) {
                // If JSON parsing fails, continue with fallback logic
                \PrestaShopLogger::addLog(
                    'PostHog cookie parsing error: ' . $e->getMessage(),
                    2,
                    null,
                    'PostHogService'
                );
            }
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
     * Build event properties with required PostHog settings
     *
     * IMPORTANT: Always sets $process_person_profile to false to avoid
     * creating full user profiles (GDPR compliance + performance)
     *
     * @param array $properties User-defined properties
     * @return array Complete properties array
     */
    private function buildEventProperties(array $properties)
    {
        // Merge user properties with required PostHog settings
        return array_merge($properties, [
            '$process_person_profile' => false,
        ]);
    }

    /**
     * Send event payload to PostHog HTTP API using Symfony HttpClient
     *
     * @param array $payload Event payload
     * @return bool True if successful, false otherwise
     */
    private function sendToPostHog(array $payload)
    {
        try {
            $response = $this->httpClient->request('POST', '/capture/', [
                'json' => $payload,
            ]);

            // PostHog returns 200 on success
            return $response->getStatusCode() === 200;
        } catch (HttpExceptionInterface $e) {
            // Log HTTP errors but don't break execution
            \PrestaShopLogger::addLog(
                'PostHog API HTTP error: ' . $e->getMessage(),
                3,
                $e->getResponse()->getStatusCode(),
                'PostHogService'
            );
            return false;
        } catch (TransportExceptionInterface $e) {
            // Log transport errors but don't break execution
            \PrestaShopLogger::addLog(
                'PostHog API transport error: ' . $e->getMessage(),
                3,
                null,
                'PostHogService'
            );
            return false;
        } catch (\Exception $e) {
            // Catch any other errors
            \PrestaShopLogger::addLog(
                'PostHog API error: ' . $e->getMessage(),
                3,
                null,
                'PostHogService'
            );
            return false;
        }
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
     * @return bool True if event sent successfully, false otherwise
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
            'currency' => isset($context->currency) && $context->currency
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
     * @return bool
     */
    public function trackOrderConfirmation($order)
    {
        $customer = new \Customer($order->id_customer);
        $distinctId = $this->getDistinctId($customer);

        $properties = [
            'order_id' => $order->id,
            'total_amount' => (float) $order->total_paid,
            'currency' => $order->id_currency ? (new \Currency($order->id_currency))->iso_code : 'EUR',
            'customer_email' => $customer->email,
        ];

        return $this->capture('Order Confirmation', $properties, $distinctId);
    }
}
