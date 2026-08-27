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

namespace Dialog\AskDialog\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class ShopRepository
 *
 * Multistore topology reads: which active countries each shop is associated with.
 */
class ShopRepository extends AbstractRepository
{
    /**
     * Active countries associated with each shop, as id_shop => [ISO, ...].
     *
     * Reads `country_shop` directly instead of switching the shop context and
     * calling Country::getCountries() once per shop: a single query for the
     * whole topology, and no context mutation from a read path.
     *
     * @return array
     */
    public function getActiveCountryIsoByShop()
    {
        $sql = 'SELECT cs.id_shop, c.iso_code
                FROM ' . $this->getPrefix() . 'country_shop cs
                INNER JOIN ' . $this->getPrefix() . 'country c
                    ON c.id_country = cs.id_country
                    AND c.active = 1';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        $byShop = [];
        foreach ($results as $row) {
            $byShop[(int) $row['id_shop']][] = \Tools::strtoupper($row['iso_code']);
        }

        return $byShop;
    }
}
