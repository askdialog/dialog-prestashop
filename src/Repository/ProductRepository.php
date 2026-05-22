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
 * Repository for product data
 * Handles bulk loading of product information
 */
class ProductRepository extends AbstractRepository
{
    /**
     * Bulk load products with multilingual data
     *
     * @param array $productIds Array of product IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     *
     * @return array Indexed by id_product
     */
    public function findByIdsWithLang(array $productIds, $idLang, $idShop)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT
                    p.id_product,
                    p.active,
                    p.date_add,
                    p.id_category_default,
                    pl.name,
                    pl.description,
                    pl.description_short,
                    pl.link_rewrite
                FROM ' . $this->getPrefix() . 'product p
                INNER JOIN ' . $this->getPrefix() . 'product_lang pl
                    ON p.id_product = pl.id_product
                    AND pl.id_lang = ' . (int) $idLang . '
                    AND pl.id_shop = ' . (int) $idShop . '
                INNER JOIN ' . $this->getPrefix() . 'product_shop ps
                    ON p.id_product = ps.id_product
                    AND ps.id_shop = ' . (int) $idShop . '
                WHERE p.id_product IN (' . $this->escapeIds($productIds) . ')';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'id_product');
    }

    /**
     * Get all product IDs for a specific shop
     *
     * @param int $idShop Shop ID
     *
     * @return array Array of product IDs
     */
    public function getProductIdsByShop($idShop)
    {
        $sql = 'SELECT p.id_product
                FROM ' . $this->getPrefix() . 'product p
                INNER JOIN ' . $this->getPrefix() . 'product_shop ps
                    ON p.id_product = ps.id_product
                WHERE ps.id_shop = ' . (int) $idShop;

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'id_product');
    }

    /**
     * Get all active product IDs across all active shops
     * Used in multistore mode for unified catalog generation
     *
     * @return array Array of product IDs
     */
    public function getAllProductIds()
    {
        $sql = 'SELECT DISTINCT ps.id_product
                FROM ' . $this->getPrefix() . 'product_shop ps
                INNER JOIN ' . $this->getPrefix() . 'shop s
                    ON ps.id_shop = s.id_shop
                    AND s.active = 1
                WHERE ps.active = 1';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'id_product');
    }

    /**
     * Get active shop IDs and names for each product
     * Used in multistore mode to populate the shops availability field
     *
     * @param array $productIds Array of product IDs
     *
     * @return array Grouped by id_product: [id_product => [['id_shop' => X, 'shop_name' => 'Y'], ...]]
     */
    public function getActiveShopsByProductIds(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT
                    ps.id_product,
                    s.id_shop,
                    s.name AS shop_name
                FROM ' . $this->getPrefix() . 'product_shop ps
                INNER JOIN ' . $this->getPrefix() . 'shop s
                    ON ps.id_shop = s.id_shop
                    AND s.active = 1
                WHERE ps.id_product IN (' . $this->escapeIds($productIds) . ')
                    AND ps.active = 1';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product');
    }

    /**
     * Bulk load products with multilingual data without filtering by shop
     * Used in multistore mode: loads all products across shops regardless of which shop triggered the export
     *
     * @param array $productIds Array of product IDs
     * @param int $idLang Language ID
     *
     * @return array Indexed by id_product
     */
    public function findByIdsWithLangAllShops(array $productIds, $idLang)
    {
        if (empty($productIds)) {
            return [];
        }

        // No id_shop filter on product_lang: a product may exist only in shop 2 or 3
        // but not in the triggering shop. GROUP BY ensures one row per product.
        $sql = 'SELECT
                    p.id_product,
                    p.active,
                    p.date_add,
                    p.id_category_default,
                    pl.name,
                    pl.description,
                    pl.description_short,
                    pl.link_rewrite
                FROM ' . $this->getPrefix() . 'product p
                INNER JOIN ' . $this->getPrefix() . 'product_lang pl
                    ON p.id_product = pl.id_product
                    AND pl.id_lang = ' . (int) $idLang . '
                WHERE p.id_product IN (' . $this->escapeIds($productIds) . ')
                GROUP BY p.id_product';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'id_product');
    }
}
