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
 * Repository for language data
 * Handles retrieval of language information
 */
class LanguageRepository extends AbstractRepository
{
    /**
     * Get all active languages
     *
     * @return array Array of languages with id_lang, name, iso_code, active
     */
    public function findAll()
    {
        $sql = 'SELECT
                    l.id_lang,
                    l.name,
                    l.iso_code,
                    l.active,
                    l.language_code,
                    l.locale
                FROM ' . $this->getPrefix() . 'lang l
                ORDER BY l.id_lang ASC';

        $results = $this->executeS($sql);

        if (!$results) {
            return [];
        }

        return $results;
    }
}
