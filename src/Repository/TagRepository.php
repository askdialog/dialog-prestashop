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
 * Repository for product tags
 * Handles bulk loading of tag data
 */
class TagRepository extends AbstractRepository
{
    /**
     * Bulk load tags for multiple products
     *
     * @param array $productIds Array of product IDs
     * @param int $idLang Language ID
     *
     * @return array Grouped by id_product
     */
    public function findByProductIds(array $productIds, $idLang)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT
                    pt.id_product,
                    t.name
                FROM ' . $this->getPrefix() . 'product_tag pt
                INNER JOIN ' . $this->getPrefix() . 'tag t
                    ON pt.id_tag = t.id_tag
                    AND pt.id_lang = t.id_lang
                WHERE pt.id_product IN (' . $this->escapeIds($productIds) . ')
                    AND pt.id_lang = ' . (int) $idLang . '
                ORDER BY pt.id_product, t.name';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product');
    }
}
