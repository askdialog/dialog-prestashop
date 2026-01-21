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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Dialog\AskDialog\Helper\PathHelper;
use Dialog\AskDialog\Repository\ExportLogRepository;
use Dialog\AskDialog\Traits\JsonResponseTrait;

/**
 * Class AskDialogDownloadlatestexportModuleFrontController
 *
 * Public endpoint for downloading the latest catalog export JSON file
 * No authentication required (product data is public)
 *
 * Route: /module/askdialog/downloadlatestexport
 */
class AskDialogDownloadlatestexportModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    /**
     * Initialize controller
     */
    public function initContent()
    {
        parent::initContent();
        $this->ajax = true;
    }

    /**
     * Main handler - streams the latest catalog export file
     */
    public function displayAjax()
    {
        $latestFile = $this->findLatestCatalogFile();

        if ($latestFile === null) {
            $this->sendJsonResponse([
                'error' => 'No catalog export file available',
            ], 404);
        }

        $this->streamFile($latestFile);
    }

    /**
     * Find the most recent successful catalog export file
     * Uses database to find latest successful export, then verifies file exists
     *
     * @return string|null Full path to the latest file, or null if none found
     */
    private function findLatestCatalogFile()
    {
        $idShop = (int) $this->context->shop->id;
        $exportLogRepo = new ExportLogRepository();

        $latestExport = $exportLogRepo->findLatestSuccessfulByType(
            $idShop,
            ExportLogRepository::EXPORT_TYPE_CATALOG
        );

        if (!$latestExport || empty($latestExport['file_name'])) {
            return null;
        }

        $filePath = PathHelper::getSentDir() . $latestExport['file_name'];

        // Verify file still exists on disk
        if (!file_exists($filePath)) {
            return null;
        }

        return $filePath;
    }

    /**
     * Stream file to client
     *
     * @param string $filePath Full path to the file
     */
    private function streamFile($filePath)
    {
        $filename = basename($filePath);
        $filesize = filesize($filePath);

        // Disable output buffering to prevent memory issues
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for file download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Stream file directly
        readfile($filePath);
        exit;
    }
}
