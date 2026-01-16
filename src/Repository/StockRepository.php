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
 * Repository for stock availability
 * Handles bulk loading of product and combination stock data
 */
class StockRepository extends AbstractRepository
{
    /**
     * Bulk load product stock (without combinations)
     *
     * @param array $productIds Array of product IDs
     * @param int $idShop Shop ID
     *
     * @return array Indexed by id_product
     */
    public function findByProductIds(array $productIds, $idShop)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT
                    sa.id_product,
                    sa.quantity,
                    sa.physical_quantity,
                    sa.reserved_quantity
                FROM ' . $this->getPrefix() . 'stock_available sa
                WHERE sa.id_product IN (' . $this->escapeIds($productIds) . ')
                    AND sa.id_product_attribute = 0
                    AND sa.id_shop = ' . (int) $idShop;

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'id_product');
    }

    /**
     * Bulk load combination stock
     *
     * @param array $combinationIds Array of combination IDs
     * @param int $idShop Shop ID
     *
     * @return array Indexed by id_product_attribute
     */
    public function findByCombinationIds(array $combinationIds, $idShop)
    {
        if (empty($combinationIds)) {
            return [];
        }

        $sql = 'SELECT
                    sa.id_product_attribute,
                    sa.quantity,
                    sa.physical_quantity,
                    sa.reserved_quantity
                FROM ' . $this->getPrefix() . 'stock_available sa
                WHERE sa.id_product_attribute IN (' . $this->escapeIds($combinationIds) . ')
                    AND sa.id_product_attribute <> 0
                    AND sa.id_shop = ' . (int) $idShop;

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'id_product_attribute');
    }
}
