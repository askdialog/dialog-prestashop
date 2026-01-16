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
 * Repository for CMS pages data
 * Handles retrieval of CMS page information
 */
class CmsRepository extends AbstractRepository
{
    /**
     * Get all CMS pages for a specific language
     *
     * @param int $idLang Language ID
     *
     * @return array Array of CMS pages with meta_title and content
     */
    public function findByLanguage($idLang)
    {
        $sql = 'SELECT
                    cl.meta_title,
                    cl.content
                FROM ' . $this->getPrefix() . 'cms_lang cl
                WHERE cl.id_lang = ' . (int) $idLang;

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $results;
    }
}
