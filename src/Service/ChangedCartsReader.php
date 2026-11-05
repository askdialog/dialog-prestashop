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

use Dialog\AskDialog\Repository\CartVisitorRepository;
use Dialog\AskDialog\Repository\CustomerContactRepository;

/**
 * Reads the carts Dialog follows, a page at a time, from a `(date_upd, id_cart)` cursor
 *
 * PrestaShop stores dates in local time to the second: the cursor travels as ISO 8601 and
 * a page stops before the current second, which can still receive writes.
 */
class ChangedCartsReader
{
    /** @var CartVisitorRepository */
    private $carts;

    /** @var CustomerContactRepository */
    private $contacts;

    /** @var \DateTimeZone */
    private $timezone;

    public function __construct()
    {
        $this->carts = new CartVisitorRepository();
        $this->contacts = new CustomerContactRepository();
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * @param float $deadline microtime after which the page closes on the carts read so far
     *
     * @return array{carts: array<int, array<string, mixed>>, nextCursor: array{updatedAfter: string, afterId: int}}
     */
    public function read(\DateTimeInterface $updatedAfter, int $afterId, int $limit, float $deadline): array
    {
        $after = (new \DateTime('@' . $updatedAfter->getTimestamp()))->setTimezone($this->timezone)->format('Y-m-d H:i:s');
        $rows = $this->carts->findChanged($after, $afterId, date('Y-m-d H:i:s'), \Shop::getCompleteListOfShopsID(), $limit);

        $customers = $this->contacts->findCustomers(array_unique(array_column($rows, 'id_customer')));
        $phones = $this->contacts->findPhones(array_unique(array_merge(
            array_column($rows, 'id_address_delivery'),
            array_column($rows, 'id_address_invoice')
        )));

        $carts = [];
        $nextCursor = ['updatedAfter' => $this->toIso($after), 'afterId' => $afterId];
        foreach ($rows as $row) {
            if ($carts !== [] && microtime(true) > $deadline) {
                break;
            }
            $carts[] = $this->toCart($row, $customers[$row['id_customer']] ?? null, $phones);
            $nextCursor = ['updatedAfter' => $this->toIso($row['changed_at']), 'afterId' => (int) $row['id_cart']];
        }

        return ['carts' => $carts, 'nextCursor' => $nextCursor];
    }

    /**
     * @param array<string, string>|null $customer
     * @param array<int|string, string> $phones
     *
     * @return array<string, mixed>
     */
    private function toCart(array $row, ?array $customer, array $phones): array
    {
        $cart = new \Cart((int) $row['id_cart']);
        // PrestaShop prices each line in the context currency; it switches the shop by itself
        $context = \Context::getContext();
        $requestCurrency = $context->currency;
        $context->currency = new \Currency((int) $row['id_currency']);
        try {
            $lines = [];
            foreach ($cart->getProducts() as $product) {
                $lines[] = [
                    'idProduct' => (int) $product['id_product'],
                    'idProductAttribute' => (int) $product['id_product_attribute'],
                    'title' => $product['name'],
                    'quantity' => (int) $product['cart_quantity'],
                    'unitPrice' => $this->toAmount((float) $product['price_wt']),
                ];
            }
            // Without shipping: pricing it would call the carrier modules, some of them remote
            $total = $this->toAmount((float) $cart->getOrderTotal(true, \Cart::BOTH_WITHOUT_SHIPPING));
        } finally {
            $context->currency = $requestCurrency;
        }

        return [
            'idCart' => (int) $row['id_cart'],
            'idShop' => (int) $row['id_shop'],
            'idCustomer' => (int) $row['id_customer'],
            'visitorId' => $row['visitor_id'],
            'consentLevel' => $row['consent_level'],
            'createdAt' => $this->toIso($row['date_add']),
            'updatedAt' => $this->toIso($row['changed_at']),
            'currency' => \Currency::getIsoCodeById((int) $row['id_currency']),
            'total' => $total,
            'lines' => $lines,
            'customer' => [
                'email' => $customer['email'] ?? null,
                'phone' => $phones[$row['id_address_delivery']] ?? $phones[$row['id_address_invoice']] ?? null,
                'isGuest' => $customer !== null ? (bool) $customer['is_guest'] : null,
            ],
        ];
    }

    /**
     * A decimal string: json_encode prints floats at serialize_precision, often 17 digits
     */
    private function toAmount(float $amount): string
    {
        return number_format($amount, 6, '.', '');
    }

    private function toIso(string $localDateTime): string
    {
        return (new \DateTime($localDateTime, $this->timezone))->format(DATE_ATOM);
    }
}
