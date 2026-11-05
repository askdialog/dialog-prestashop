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

use Dialog\AskDialog\Service\CartVisitorLink;
use Dialog\AskDialog\Traits\JsonResponseTrait;

/**
 * Class AskDialogIdentityModuleFrontController
 *
 * The widget posts its visitor here once the shopper consents: the module keeps it in the
 * PrestaShop cookie and links it to each cart the shopper saves (actionCartSave).
 */
class AskDialogIdentityModuleFrontController extends ModuleFrontController
{
    use JsonResponseTrait;

    public function __construct()
    {
        parent::__construct();
        // Before init(), which then skips the storefront header and its media hook
        $this->ajax = true;
    }

    public function displayAjax()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['error' => 'Method not allowed'], 405);
        }

        if (!hash_equals(Tools::getToken(false), (string) Tools::getValue('token'))) {
            $this->sendJsonResponse(['error' => 'Invalid token'], 403);
        }

        $visitorId = (string) Tools::getValue('userId');
        $consentLevel = (string) Tools::getValue('consentLevel');
        if (!CartVisitorLink::isValid($visitorId, $consentLevel)) {
            $this->sendJsonResponse(['error' => 'Invalid userId or consentLevel'], 400);
        }

        (new CartVisitorLink())->remember($this->context, $visitorId, $consentLevel);

        http_response_code(204);
        exit;
    }
}
