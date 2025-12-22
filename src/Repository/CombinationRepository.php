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
 * Repository for product combinations/variants
 * Handles bulk loading of combination data and attributes
 *
 * @package Dialog\AskDialog\Repository
 */
class CombinationRepository extends AbstractRepository
{
    /**
     * Bulk load combinations for multiple products
     *
     * @param array $productIds Array of product IDs
     * @return array Grouped by id_product
     */
    public function findByProductIds(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT 
                    pa.id_product_attribute,
                    pa.id_product,
                    pa.reference,
                    pa.price,
                    pa.weight
                FROM ' . $this->getPrefix() . 'product_attribute pa
                WHERE pa.id_product IN (' . $this->escapeIds($productIds) . ')
                ORDER BY pa.id_product, pa.id_product_attribute';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product');
    }

    /**
     * Bulk load attribute details for combinations
     * Returns combination attributes with group names and attribute names
     *
     * @param array $combinationIds Array of combination IDs (id_product_attribute)
     * @param int $idLang Language ID
     * @return array Grouped by id_product_attribute
     */
    public function findAttributesByCombinationIds(array $combinationIds, $idLang)
    {
        if (empty($combinationIds)) {
            return [];
        }

        $sql = 'SELECT 
                    pac.id_product_attribute,
                    pac.id_attribute,
                    al.name AS attribute_name,
                    agl.public_name AS group_name
                FROM ' . $this->getPrefix() . 'product_attribute_combination pac
                INNER JOIN ' . $this->getPrefix() . 'attribute a 
                    ON pac.id_attribute = a.id_attribute
                INNER JOIN ' . $this->getPrefix() . 'attribute_lang al 
                    ON a.id_attribute = al.id_attribute 
                    AND al.id_lang = ' . (int)$idLang . '
                INNER JOIN ' . $this->getPrefix() . 'attribute_group ag 
                    ON a.id_attribute_group = ag.id_attribute_group
                INNER JOIN ' . $this->getPrefix() . 'attribute_group_lang agl 
                    ON ag.id_attribute_group = agl.id_attribute_group 
                    AND agl.id_lang = ' . (int)$idLang . '
                WHERE pac.id_product_attribute IN (' . $this->escapeIds($combinationIds) . ')
                ORDER BY pac.id_product_attribute, ag.position, a.position';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product_attribute');
    }

    /**
     * Get all combination IDs for multiple products
     *
     * @param array $productIds Array of product IDs
     * @return array Array of id_product_attribute values
     */
    public function getCombinationIdsByProductIds(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT id_product_attribute
                FROM ' . $this->getPrefix() . 'product_attribute
                WHERE id_product IN (' . $this->escapeIds($productIds) . ')';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'id_product_attribute');
    }
}
