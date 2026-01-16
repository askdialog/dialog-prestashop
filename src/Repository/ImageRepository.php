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
 * Repository for product images
 * Handles bulk loading of product and combination images
 */
class ImageRepository extends AbstractRepository
{
    /**
     * Bulk load product images
     *
     * @param array $productIds Array of product IDs
     * @param int $idShop Shop ID
     *
     * @return array Grouped by id_product
     */
    public function findByProductIds(array $productIds, $idShop)
    {
        if (empty($productIds)) {
            return [];
        }

        $sql = 'SELECT
                    i.id_image,
                    i.id_product,
                    i.position,
                    COALESCE(ishop.cover, i.cover) AS cover
                FROM ' . $this->getPrefix() . 'image i
                LEFT JOIN ' . $this->getPrefix() . 'image_shop ishop
                    ON i.id_image = ishop.id_image
                    AND ishop.id_shop = ' . (int) $idShop . '
                WHERE i.id_product IN (' . $this->escapeIds($productIds) . ')
                ORDER BY i.id_product, i.position';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product');
    }

    /**
     * Bulk load combination images
     *
     * @param array $combinationIds Array of combination IDs
     *
     * @return array Grouped by id_product_attribute
     */
    public function findByCombinationIds(array $combinationIds)
    {
        if (empty($combinationIds)) {
            return [];
        }

        $sql = 'SELECT
                    pai.id_product_attribute,
                    pai.id_image,
                    i.position
                FROM ' . $this->getPrefix() . 'product_attribute_image pai
                INNER JOIN ' . $this->getPrefix() . 'image i
                    ON pai.id_image = i.id_image
                WHERE pai.id_product_attribute IN (' . $this->escapeIds($combinationIds) . ')
                ORDER BY pai.id_product_attribute, i.position';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product_attribute');
    }
}
