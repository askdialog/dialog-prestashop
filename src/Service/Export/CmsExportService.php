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

namespace Dialog\AskDialog\Service\Export;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\CmsRepository;

/**
 * Service responsible for CMS pages export
 * Handles both file generation and API data retrieval
 */
class CmsExportService
{
    private $cmsRepository;

    public function __construct()
    {
        $this->cmsRepository = new CmsRepository();
    }

    /**
     * Generates CMS pages data and saves to JSON file
     *
     * @param int|null $idLang Language ID (default: shop default language)
     *
     * @return string Path to generated JSON file
     */
    public function generateFile($idLang = null)
    {
        if ($idLang === null) {
            $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        // Retrieve all CMS pages
        $cmsPages = $this->cmsRepository->findByLanguage($idLang);

        $cmsData = [];
        foreach ($cmsPages as $page) {
            $cmsData[] = [
                'title' => $page['meta_title'],
                'content' => $page['content'],
            ];
        }

        // Generate unique file path
        $tmpFile = PathHelper::generateTmpFilePath('cms');

        // JSON optimized for LLM: unescaped unicode/slashes, pretty print for readability
        $jsonData = json_encode($cmsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($tmpFile, $jsonData);

        return $tmpFile;
    }

    /**
     * Returns CMS pages data for API consumption (no file generation)
     *
     * @param int|null $idLang Language ID (default: shop default language)
     *
     * @return array CMS pages data
     */
    public function getData($idLang = null)
    {
        if ($idLang === null) {
            $idLang = (int) \Configuration::get('PS_LANG_DEFAULT');
        }

        $cmsPages = $this->cmsRepository->findByLanguage($idLang);

        $cmsData = [];
        foreach ($cmsPages as $page) {
            $cmsData[] = [
                'title' => $page['meta_title'],
                'content' => $page['content'],
            ];
        }

        return $cmsData;
    }
}
