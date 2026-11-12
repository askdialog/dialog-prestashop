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

use Dialog\AskDialog\Helper\Logger;
use Dialog\AskDialog\Service\ChangedCartsReader;
use Dialog\AskDialog\Service\ShopperOrdersReader;
use Dialog\AskDialog\Traits\ApiKeyAuthTrait;
use Dialog\AskDialog\Traits\JsonResponseTrait;

/**
 * Class AskDialogShoppersModuleFrontController
 *
 * What Dialog reads to build the Shoppers page: the module's version and actions, then the
 * carts, orders and credit slips of identified shoppers changed since a cursor, and a
 * shopper's order history when Dialog first links them.
 */
class AskDialogShoppersModuleFrontController extends ModuleFrontController
{
    use ApiKeyAuthTrait;
    use JsonResponseTrait;

    private const ACTIONS = ['getModuleInfo', 'getCarts', 'getOrders', 'getCreditSlips', 'getCustomerOrders'];

    /**
     * What the module serves outside this controller, listed with its actions: `handoff` is
     * the cart handoff controller, which Dialog checks before signing links for this store
     */
    private const OTHER_CAPABILITIES = ['handoff'];

    private const DEFAULT_LIMIT = 100;

    private const MAX_LIMIT = 500;

    /**
     * Seconds a page may spend pricing carts: the caller gives up after 75
     */
    private const TIME_BUDGET = 60;

    private const ISO_8601 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/';

    public function __construct()
    {
        parent::__construct();
        // Before init(), which then skips the storefront header and its media hook
        $this->ajax = true;
    }

    public function initContent()
    {
        parent::initContent();

        $this->requireApiKey('ASKDIALOG_API_KEY', 'Private');
    }

    public function displayAjax()
    {
        switch (Tools::getValue('action')) {
            case 'getModuleInfo':
                $this->sendJsonResponse([
                    'version' => $this->module->version,
                    'actions' => array_merge(self::ACTIONS, self::OTHER_CAPABILITIES),
                ]);
                break;

            case 'getCarts':
                list($updatedAfter, $afterId, $limit) = $this->readCursor();
                $this->respond('carts', function () use ($updatedAfter, $afterId, $limit) {
                    return (new ChangedCartsReader())->read($updatedAfter, $afterId, $limit, $this->getDeadline());
                });
                break;

            case 'getOrders':
                list($updatedAfter, $afterId, $limit) = $this->readCursor();
                $this->respond('orders', function () use ($updatedAfter, $afterId, $limit) {
                    return (new ShopperOrdersReader())->readChanged($updatedAfter, $afterId, $limit);
                });
                break;

            case 'getCreditSlips':
                list($updatedAfter, $afterId, $limit) = $this->readCursor();
                $this->respond('credit slips', function () use ($updatedAfter, $afterId, $limit) {
                    return (new ShopperOrdersReader())->readChangedCreditSlips($updatedAfter, $afterId, $limit);
                });
                break;

            case 'getCustomerOrders':
                $idCustomer = (string) Tools::getValue('idCustomer');
                if (!ctype_digit($idCustomer) || (int) $idCustomer < 1) {
                    $this->sendJsonResponse(['error' => 'idCustomer must be a customer id'], 400);
                }
                $this->respond('customer orders', function () use ($idCustomer) {
                    return (new ShopperOrdersReader())->readCustomerHistory((int) $idCustomer);
                });
                break;

            default:
                $this->sendJsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * The `updatedAfter`, `afterId` and `limit` of a cursor read, or a 400
     *
     * @return array{0: DateTime, 1: int, 2: int}
     */
    private function readCursor(): array
    {
        $updatedAfter = $this->parseDate((string) Tools::getValue('updatedAfter'));
        $afterId = (string) Tools::getValue('afterId', '0');
        $limit = (string) Tools::getValue('limit', (string) self::DEFAULT_LIMIT);

        if ($updatedAfter === null
            || !ctype_digit($afterId)
            || !ctype_digit($limit)
            || (int) $limit < 1
            || (int) $limit > self::MAX_LIMIT
        ) {
            $this->sendJsonResponse([
                'error' => 'updatedAfter must be ISO 8601 with an offset, afterId an id, limit 1 to ' . self::MAX_LIMIT,
            ], 400);
        }

        return [$updatedAfter, (int) $afterId, (int) $limit];
    }

    private function respond(string $what, callable $read)
    {
        try {
            $page = $read();
        } catch (Throwable $e) {
            Logger::log('[AskDialog] Shoppers: ' . $what . ' read failed - ' . $e->getMessage(), 3);
            $this->sendJsonResponse(['error' => ucfirst($what) . ' could not be read'], 500);
        }

        $this->sendJsonResponse($page);
    }

    /**
     * An ISO 8601 date with an offset; null for any other value, including an impossible date
     */
    private function parseDate(string $value): ?DateTime
    {
        if (!preg_match(self::ISO_8601, $value)) {
            return null;
        }

        $date = DateTime::createFromFormat('Y-m-d\TH:i:sP', preg_replace(['/\.\d+/', '/Z$/'], ['', '+00:00'], $value));
        $errors = DateTime::getLastErrors();
        if ($date === false || (is_array($errors) && $errors['warning_count'] > 0)) {
            return null;
        }

        return $date;
    }

    private function getDeadline(): float
    {
        $budget = self::TIME_BUDGET;
        $maxExecutionTime = (int) ini_get('max_execution_time');
        if ($maxExecutionTime > 0) {
            $budget = max(5, min($budget, $maxExecutionTime - 10));
        }

        return $_SERVER['REQUEST_TIME_FLOAT'] + $budget;
    }
}
