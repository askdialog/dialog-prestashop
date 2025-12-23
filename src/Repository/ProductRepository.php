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

namespace Dialog\AskDialog\Repository;

/**
 * Repository for product data
 * Handles bulk loading of product information
 *
 * @package Dialog\AskDialog\Repository
 */
class ProductRepository extends AbstractRepository
{
    /**
     * Bulk load products with multilingual data
     *
     * @param array $productIds Array of product IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
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
                    AND pl.id_lang = ' . (int)$idLang . '
                    AND pl.id_shop = ' . (int)$idShop . '
                INNER JOIN ' . $this->getPrefix() . 'product_shop ps 
                    ON p.id_product = ps.id_product 
                    AND ps.id_shop = ' . (int)$idShop . '
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
     * @return array Array of product IDs
     */
    public function getProductIdsByShop($idShop)
    {
        $sql = 'SELECT p.id_product
                FROM ' . $this->getPrefix() . 'product p
                INNER JOIN ' . $this->getPrefix() . 'product_shop ps 
                    ON p.id_product = ps.id_product
                WHERE ps.id_shop = ' . (int)$idShop;

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'id_product');
    }
}
