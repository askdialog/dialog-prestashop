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
 * Repository for product categories
 * Handles bulk loading of category data and product-category relations
 *
 * @package Dialog\AskDialog\Repository
 */
class CategoryRepository extends AbstractRepository
{
    /**
     * Bulk load product-category relations
     *
     * @param array $productIds Array of product IDs
     * @return array Grouped by id_product
     */
    public function findCategoryIdsByProductIds(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT 
                    cp.id_product,
                    cp.id_category
                FROM ' . $this->getPrefix() . 'category_product cp
                WHERE cp.id_product IN (' . $this->escapeIds($productIds) . ')
                ORDER BY cp.id_product, cp.position';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product');
    }

    /**
     * Bulk load category details with multilingual data
     *
     * @param array $categoryIds Array of category IDs
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     * @return array Indexed by id_category
     */
    public function findByIds(array $categoryIds, $idLang, $idShop)
    {
        if (empty($categoryIds)) {
            return [];
        }

        $sql = 'SELECT 
                    c.id_category,
                    c.active,
                    cl.name,
                    cl.description,
                    cl.link_rewrite
                FROM ' . $this->getPrefix() . 'category c
                INNER JOIN ' . $this->getPrefix() . 'category_lang cl 
                    ON c.id_category = cl.id_category 
                    AND cl.id_lang = ' . (int)$idLang . '
                    AND cl.id_shop = ' . (int)$idShop . '
                WHERE c.id_category IN (' . $this->escapeIds($categoryIds) . ')
                    AND c.active = 1';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'id_category');
    }
}
