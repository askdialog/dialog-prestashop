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

namespace Dialog\AskDialog\Service\Export;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Repository\ShopRepository;

/**
 * Class ShopMarketMap
 *
 * Maps the multistore topology onto Dialog markets.
 *
 * Dialog keys prices, availability and URLs by ISO country — one market per
 * country, no per-organization configuration. A PrestaShop shop is therefore
 * expressed as *the set of countries it owns*: it contributes those countries'
 * prices, in its own currency and with its own tax display.
 *
 * Each shop carries two country sets, and the distinction is load-bearing:
 *  - `servedCountries`: every country the shop is associated with. Shops overlap
 *    here, because the same table drives which countries a shopper may ship to.
 *  - `ownedCountries`: after tie-breaking, the countries this shop is the pricing
 *    authority for. Disjoint across shops by construction — a country can only
 *    carry one amount under one key.
 */
class ShopMarketMap
{
    /** Matches the ingestion fallback for a shop with no readable currency. */
    public const FALLBACK_CURRENCY = 'EUR';

    /** @var ShopRepository */
    private $shopRepository;

    /** @var array|null id_shop => descriptor, built once per request */
    private $shops;

    /** @var string[] Ownership decisions the topology could not make on its own */
    private $ambiguities = [];

    public function __construct(?ShopRepository $shopRepository = null)
    {
        $this->shopRepository = $shopRepository !== null ? $shopRepository : new ShopRepository();
    }

    /**
     * Whether the catalog spans several active shops. Drives every multistore
     * branch: a single-shop install must keep the previous behaviour exactly.
     *
     * Requires the multistore feature itself: with it off, PrestaShop ignores
     * the shop argument of Configuration::get() and serves every request from
     * the default shop, so the per-shop topology would be read from one shop
     * and labelled as three.
     *
     * @return bool
     */
    public function isMultishop()
    {
        return \Shop::isFeatureActive() && count($this->getShops()) > 1;
    }

    /**
     * A configuration value as one shop resolves it: shop row, else its own
     * shop group's row, else global. Configuration::get() substitutes the
     * *ambient* shop group when none is given, so a shop living in another
     * group than the request's would otherwise read a foreign group's value.
     *
     * @param string $key
     * @param int $idShop
     *
     * @return mixed
     */
    public static function shopConfiguration($key, $idShop)
    {
        $idShopGroup = \Shop::getGroupFromShop((int) $idShop);

        return \Configuration::get($key, null, $idShopGroup ? (int) $idShopGroup : null, (int) $idShop);
    }

    /**
     * Ownership decisions that fell back to the lowest shop id. Collected
     * rather than logged here: the topology is rebuilt on every storefront
     * render, which would turn each of these into one log row per page view.
     *
     * @return string[]
     */
    public function getAmbiguities()
    {
        $this->getShops();

        return $this->ambiguities;
    }

    /**
     * @return array id_shop => ['idShop', 'name', 'currency', 'defaultCountry', 'servedCountries', 'ownedCountries']
     */
    public function getShops()
    {
        if ($this->shops === null) {
            $this->shops = $this->build();
        }

        return $this->shops;
    }

    /**
     * @param int $idShop
     *
     * @return array|null
     */
    public function getShop($idShop)
    {
        $shops = $this->getShops();
        $idShop = (int) $idShop;

        return isset($shops[$idShop]) ? $shops[$idShop] : null;
    }

    /**
     * Country the widget must report for a shopper browsing this shop.
     *
     * The shopper's own country when this shop *owns* it — that is what keeps
     * per-country VAT working inside a shop, and it covers every country the
     * shop is the pricing authority for.
     *
     * Otherwise a country the shop does own. Quoting a country owned by another
     * shop would put that shop's currency against this page — a price that
     * exists nowhere on this storefront. Falling back keeps the currency and the
     * price list of the shop the shopper is actually on; at worst it applies
     * another country's tax jurisdiction, which is the lesser error.
     *
     * Returns the visitor country untouched outside multistore: an install with
     * the feature enabled but a single shop must keep resolving tax by the
     * visitor's own country, exactly as before. The feature check comes first
     * and short-circuits, so a plain install never builds the topology — this
     * runs on every storefront render.
     *
     * @param int $idShop
     * @param string|null $visitorIso
     *
     * @return string|null
     */
    public function boundCountryIso($idShop, $visitorIso)
    {
        if (!$this->isMultishop()) {
            return $visitorIso;
        }

        $shop = $this->getShop($idShop);
        if ($shop === null || empty($shop['ownedCountries'])) {
            return $visitorIso;
        }

        $visitorIso = \Tools::strtoupper((string) $visitorIso);
        if ($visitorIso !== '' && in_array($visitorIso, $shop['ownedCountries'], true)) {
            return $visitorIso;
        }

        if ($shop['defaultCountry'] !== null
            && in_array($shop['defaultCountry'], $shop['ownedCountries'], true)) {
            return $shop['defaultCountry'];
        }

        return $shop['ownedCountries'][0];
    }

    /**
     * Countries where a product is not for sale: those owned by the shops that
     * do not carry it. Empty outside multistore, or when every shop carries it.
     *
     * @param array $availableShopIds Shops the product is active on
     *
     * @return array
     */
    public function excludedMarkets(array $availableShopIds)
    {
        if (!$this->isMultishop()) {
            return [];
        }

        $available = array_map('intval', $availableShopIds);
        $excluded = [];

        foreach ($this->getShops() as $idShop => $shop) {
            if (in_array((int) $idShop, $available, true)) {
                continue;
            }
            foreach ($shop['ownedCountries'] as $iso) {
                $excluded[] = $iso;
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * @return array
     */
    private function build()
    {
        $countriesByShop = $this->shopRepository->getActiveCountryIsoByShop();
        $descriptors = [];

        foreach (\Shop::getShops(true) as $shop) {
            $idShop = (int) $shop['id_shop'];
            $descriptors[$idShop] = [
                'idShop' => $idShop,
                'name' => isset($shop['name']) ? $shop['name'] : ('Shop ' . $idShop),
                'currency' => $this->resolveCurrencyIso($idShop),
                'defaultCountry' => $this->resolveDefaultCountryIso($idShop),
                'servedCountries' => isset($countriesByShop[$idShop]) ? $countriesByShop[$idShop] : [],
                'ownedCountries' => [],
            ];
        }

        // Lowest id first, so the fallback tie-break below is deterministic
        // whatever order Shop::getShops() returns.
        ksort($descriptors);

        return $this->assignOwnership($descriptors);
    }

    /**
     * Give every country to exactly one shop: the one it is the default country
     * of, else the lowest shop id. Without this a country served by two shops
     * would carry two different amounts under a single market key.
     *
     * @param array $descriptors
     *
     * @return array
     */
    private function assignOwnership(array $descriptors)
    {
        $ownerByCountry = [];

        foreach ($descriptors as $idShop => $descriptor) {
            foreach ($descriptor['servedCountries'] as $iso) {
                if (!isset($ownerByCountry[$iso])) {
                    $ownerByCountry[$iso] = $idShop;
                    continue;
                }

                $incumbent = $ownerByCountry[$iso];
                $incumbentClaims = $descriptors[$incumbent]['defaultCountry'] === $iso;
                $challengerClaims = $descriptor['defaultCountry'] === $iso;

                // Two shops declaring the same default country cannot be told
                // apart. Keep the lower id so ownership never depends on the
                // order Shop::getShops() happens to return.
                if ($incumbentClaims && $challengerClaims) {
                    $this->ambiguities[] = 'shops ' . $incumbent . ' and ' . $idShop
                        . ' both declare ' . $iso . ' as their default country; keeping shop ' . $incumbent;
                    continue;
                }

                if ($challengerClaims) {
                    $ownerByCountry[$iso] = $idShop;
                    continue;
                }
                if ($incumbentClaims) {
                    continue;
                }

                $this->ambiguities[] = 'country ' . $iso . ' is served by shops ' . $incumbent
                    . ' and ' . $idShop . ' and is the default country of neither; keeping shop ' . $incumbent;
            }
        }

        foreach ($ownerByCountry as $iso => $idShop) {
            $descriptors[$idShop]['ownedCountries'][] = $iso;
        }

        return $descriptors;
    }

    /**
     * @param int $idShop
     *
     * @return string
     */
    private function resolveCurrencyIso($idShop)
    {
        $idCurrency = (int) self::shopConfiguration('PS_CURRENCY_DEFAULT', $idShop);
        if ($idCurrency <= 0) {
            return self::FALLBACK_CURRENCY;
        }

        $currency = new \Currency($idCurrency);

        return !empty($currency->iso_code) ? $currency->iso_code : self::FALLBACK_CURRENCY;
    }

    /**
     * @param int $idShop
     *
     * @return string|null
     */
    private function resolveDefaultCountryIso($idShop)
    {
        $idCountry = (int) self::shopConfiguration('PS_COUNTRY_DEFAULT', $idShop);
        if ($idCountry <= 0) {
            return null;
        }

        $iso = \Country::getIsoById($idCountry);

        return !empty($iso) ? \Tools::strtoupper($iso) : null;
    }
}
