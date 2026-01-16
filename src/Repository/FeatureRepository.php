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
 * Repository for product features (characteristics)
 * Handles bulk loading of feature data
 */
class FeatureRepository extends AbstractRepository
{
    /**
     * Bulk load features for multiple products
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
                    fp.id_product,
                    fl.name AS feature_name,
                    fvl.value AS feature_value
                FROM ' . $this->getPrefix() . 'feature_product fp
                INNER JOIN ' . $this->getPrefix() . 'feature f
                    ON fp.id_feature = f.id_feature
                INNER JOIN ' . $this->getPrefix() . 'feature_lang fl
                    ON f.id_feature = fl.id_feature
                    AND fl.id_lang = ' . (int) $idLang . '
                INNER JOIN ' . $this->getPrefix() . 'feature_value fv
                    ON fp.id_feature_value = fv.id_feature_value
                INNER JOIN ' . $this->getPrefix() . 'feature_value_lang fvl
                    ON fv.id_feature_value = fvl.id_feature_value
                    AND fvl.id_lang = ' . (int) $idLang . '
                WHERE fp.id_product IN (' . $this->escapeIds($productIds) . ')
                ORDER BY fp.id_product, f.position';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $this->groupBy($results, 'id_product');
    }
}
