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

namespace Dialog\AskDialog\Service\Export;

use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\CategoryRepository;

/**
 * Service responsible for category data export
 * Handles both file generation and API data retrieval
 *
 * @package Dialog\AskDialog\Service\Export
 */
class CategoryExportService
{
    private $categoryRepository;

    public function __construct()
    {
        $this->categoryRepository = new CategoryRepository();
    }

    /**
     * Generates category data and saves to JSON file
     *
     * @param int $idLang Language ID
     * @param int $idShop Shop ID
     * @return string Path to generated JSON file
     */
    public function generateFile($idLang, $idShop)
    {
        $categories = $this->categoryRepository->findAllForExport($idLang, $idShop);
        $categoryTree = $this->buildCategoryTree($categories);

        // Generate unique file path
        $tmpFile = PathHelper::generateTmpFilePath('category');

        // JSON optimized for LLM: unescaped unicode/slashes, pretty print for readability
        $jsonData = json_encode($categoryTree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents($tmpFile, $jsonData);

        return $tmpFile;
    }

    /**
     * Returns category data for API consumption (no file generation)
     *
     * @param int $idShop Shop ID
     * @param int $idLang Language ID
     * @return array Category tree structure
     */
    public function getData($idShop, $idLang)
    {
        $categories = $this->categoryRepository->findAllForExport($idLang, $idShop);
        return $this->buildCategoryTree($categories);
    }

    /**
     * Builds nested category tree from flat array
     *
     * @param array $categories Flat array of categories
     * @return array Nested tree structure
     */
    private function buildCategoryTree($categories)
    {
        if (empty($categories)) {
            return [];
        }

        // Build index by id_category and prepare children array
        $index = [];
        foreach ($categories as $category) {
            $category['children'] = [];
            $category['url'] = '/' . $category['id_category'] . '-' . $category['link_rewrite'];

            // Remove internal fields not needed for LLM
            unset($category['link_rewrite']);
            unset($category['position']);

            $index[$category['id_category']] = $category;
        }

        // Build tree by attaching children to parents
        $roots = [];
        foreach ($index as $id => &$category) {
            $parentId = $category['id_parent'];

            if (isset($index[$parentId])) {
                // Attach to parent
                $index[$parentId]['children'][] = &$category;
            } else {
                // Root category (no parent or parent not in active categories)
                $roots[] = &$category;
            }
        }

        // Remove id_parent from output (redundant in nested structure)
        $this->removeParentIds($roots);

        return $roots;
    }

    /**
     * Recursively remove id_parent field from category tree
     *
     * @param array &$categories Category tree (by reference)
     * @return void
     */
    private function removeParentIds(&$categories)
    {
        foreach ($categories as &$category) {
            unset($category['id_parent']);

            if (!empty($category['children'])) {
                $this->removeParentIds($category['children']);
            }
        }
    }
}
