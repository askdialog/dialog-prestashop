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
 * Repository for product categories
 * Handles bulk loading of category data and product-category relations
 */
class CategoryRepository extends AbstractRepository
{
    /**
     * Bulk load product-category relations
     *
     * @param array $productIds Array of product IDs
     *
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
     *
     * @return array Indexed by id_category
     */
    public function findByIds(array $categoryIds, $idLang, $idShop)
    {
        if (empty($categoryIds)) {
            return [];
        }

        // Check if additional_description exists (PS 8+)
        $hasAdditionalDesc = $this->columnExists('category_lang', 'additional_description');

        $sql = 'SELECT
                    c.id_category,
                    c.active,
                    cl.name,
                    cl.description,
                    ' . ($hasAdditionalDesc ? 'cl.additional_description,' : '') . '
                    cl.link_rewrite
                FROM ' . $this->getPrefix() . 'category c
                INNER JOIN ' . $this->getPrefix() . 'category_lang cl
                    ON c.id_category = cl.id_category
                    AND cl.id_lang = ' . (int) $idLang . '
                    AND cl.id_shop = ' . (int) $idShop . '
                WHERE c.id_category IN (' . $this->escapeIds($categoryIds) . ')
                    AND c.active = 1';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->indexBy($results, 'id_category');
    }

    /**
     * Load all active categories for export with full multilingual data
     *
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     *
     * @return array Array of categories with all fields
     */
    public function findAllForExport($idLang, $idShop)
    {
        $sql = 'SELECT
                    c.id_category,
                    c.id_parent,
                    cs.position,
                    cl.name,
                    cl.description,
                    cl.additional_description,
                    cl.link_rewrite,
                    cl.meta_title,
                    cl.meta_description
                FROM ' . $this->getPrefix() . 'category c
                INNER JOIN ' . $this->getPrefix() . 'category_lang cl
                    ON c.id_category = cl.id_category
                    AND cl.id_lang = ' . (int) $idLang . '
                    AND cl.id_shop = ' . (int) $idShop . '
                INNER JOIN ' . $this->getPrefix() . 'category_shop cs
                    ON c.id_category = cs.id_category
                    AND cs.id_shop = ' . (int) $idShop . '
                WHERE c.active = 1
                ORDER BY c.id_parent ASC, cs.position ASC';

        $results = $this->executeS($sql);

        return $results ?: [];
    }
}
