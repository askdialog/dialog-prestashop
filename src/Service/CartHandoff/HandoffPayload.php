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

namespace Dialog\AskDialog\Service\CartHandoff;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A cart handed over by Dialog in a signed link, version 1
 *
 * p = base64url(JSON) without padding, s = hex(HMAC-SHA256(p)) under a key derived from the
 * private API key, which only Dialog's servers hold. The received `p` is what gets signed,
 * never a re-encoded JSON. The contract is shared with the monolith and its test vectors
 * (tests/fixtures/handoff-v1-vectors.json).
 */
class HandoffPayload
{
    public const MAX_LINES = 50;

    public const MAX_QUANTITY = 99;

    /**
     * Seconds a link lives, from its signature
     */
    public const LIFETIME = 900;

    /**
     * Seconds of clock difference allowed between Dialog and the store
     */
    public const CLOCK_TOLERANCE = 60;

    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    private const VERSION = 1;

    private const KEY_CONTEXT = 'dialog-cart-handoff-v1';

    private const MAX_ENCODED_LENGTH = 16384;

    private const ID = '/^[1-9][0-9]{0,9}$/';

    private const ORIGIN = '/^[a-z0-9_]{1,32}$/';

    private const REF = '/^[A-Za-z0-9_-]{1,64}$/';

    private const UTM_VALUE = '/^[A-Za-z0-9._-]{1,100}$/';

    /** @var array<int, array{id: int, variant: int, qty: int}> */
    private $lines;

    /** @var string */
    private $origin;

    /** @var string */
    private $ref;

    /** @var array<string, string> */
    private $utm;

    private function __construct(array $lines, string $origin, string $ref, array $utm)
    {
        $this->lines = $lines;
        $this->origin = $origin;
        $this->ref = $ref;
        $this->utm = $utm;
    }

    /**
     * The payload of a link, once its signature, version and expiry hold
     *
     * @param int $now unix seconds
     *
     * @throws InvalidHandoffException
     */
    public static function verify(string $encoded, string $signature, string $privateKey, int $now): self
    {
        if ($encoded === '' || $signature === '' || $privateKey === '') {
            throw new InvalidHandoffException('missing');
        }
        if (strlen($encoded) > self::MAX_ENCODED_LENGTH || !preg_match('/^[A-Za-z0-9_-]+$/', $encoded)) {
            throw new InvalidHandoffException('malformed');
        }

        $derivedKey = hash_hmac('sha256', self::KEY_CONTEXT, $privateKey, true);
        if (!hash_equals(hash_hmac('sha256', $encoded, $derivedKey), $signature)) {
            throw new InvalidHandoffException('signature');
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data)) {
            throw new InvalidHandoffException('malformed');
        }
        if (!isset($data['v']) || $data['v'] !== self::VERSION) {
            throw new InvalidHandoffException('version');
        }

        self::checkExpiry(isset($data['exp']) ? $data['exp'] : null, $now);

        $origin = isset($data['origin']) ? $data['origin'] : null;
        if (!is_string($origin) || !preg_match(self::ORIGIN, $origin)) {
            throw new InvalidHandoffException('origin');
        }
        $ref = isset($data['ref']) ? $data['ref'] : null;
        if (!is_string($ref) || !preg_match(self::REF, $ref)) {
            throw new InvalidHandoffException('ref');
        }

        return new self(
            self::readLines(isset($data['items']) ? $data['items'] : null),
            $origin,
            $ref,
            self::readUtm(isset($data['utm']) ? $data['utm'] : null)
        );
    }

    /**
     * @return array<int, array{id: int, variant: int, qty: int}> variant 0 for a product without combinations
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * Dialog's id of the link, unique per link
     */
    public function getRef(): string
    {
        return $this->ref;
    }

    /**
     * The UTM parameters the redirect may carry: known keys with safe values only
     *
     * @return array<string, string>
     */
    public function getUtm(): array
    {
        return $this->utm;
    }

    /**
     * @param mixed $exp
     */
    private static function checkExpiry($exp, int $now)
    {
        if (!is_int($exp)) {
            throw new InvalidHandoffException('malformed');
        }
        if ($now > $exp + self::CLOCK_TOLERANCE) {
            throw new InvalidHandoffException('expired');
        }
        // A link never lives longer than it was signed for
        if ($exp > $now + self::LIFETIME + self::CLOCK_TOLERANCE) {
            throw new InvalidHandoffException('too_far');
        }
    }

    /**
     * @param mixed $items
     *
     * @return array<int, array{id: int, variant: int, qty: int}>
     */
    private static function readLines($items): array
    {
        if (!is_array($items) || $items === [] || count($items) > self::MAX_LINES || array_values($items) !== $items) {
            throw new InvalidHandoffException('items');
        }

        $lines = [];
        foreach ($items as $item) {
            $lines[] = self::readLine($item);
        }

        return $lines;
    }

    /**
     * @param mixed $item
     *
     * @return array{id: int, variant: int, qty: int}
     */
    private static function readLine($item): array
    {
        if (!is_array($item)) {
            throw new InvalidHandoffException('items');
        }

        $id = isset($item['id']) ? $item['id'] : null;
        // The signer omits the variant of a product without combinations; an explicit null is malformed
        $hasVariant = array_key_exists('variant', $item);
        $variant = $hasVariant ? $item['variant'] : null;
        $quantity = isset($item['qty']) ? $item['qty'] : null;

        if (!is_string($id) || !preg_match(self::ID, $id)
            || ($hasVariant && (!is_string($variant) || !preg_match(self::ID, $variant)))
            || !is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY
        ) {
            throw new InvalidHandoffException('items');
        }

        return ['id' => (int) $id, 'variant' => $hasVariant ? (int) $variant : 0, 'qty' => $quantity];
    }

    /**
     * @param mixed $utm
     *
     * @return array<string, string>
     */
    private static function readUtm($utm): array
    {
        if (!is_array($utm)) {
            return [];
        }

        $kept = [];
        foreach (self::UTM_KEYS as $key) {
            if (isset($utm[$key]) && is_string($utm[$key]) && preg_match(self::UTM_VALUE, $utm[$key])) {
                $kept[$key] = $utm[$key];
            }
        }

        return $kept;
    }
}
