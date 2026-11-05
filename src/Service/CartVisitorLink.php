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

namespace Dialog\AskDialog\Service;

use Dialog\AskDialog\Repository\CartVisitorRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Carries the visitor the widget reports, after consent, from the shopper's cookie to their cart
 */
class CartVisitorLink
{
    private const COOKIE_VISITOR = 'askdialog_visitor';
    private const COOKIE_CONSENT = 'askdialog_consent';

    /**
     * The widget's user id: a UUID today, any opaque id it may send tomorrow
     */
    private const VISITOR_ID_PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/';

    /**
     * @var CartVisitorRepository
     */
    private $repository;

    public function __construct(?CartVisitorRepository $repository = null)
    {
        $this->repository = $repository ?: new CartVisitorRepository();
    }

    public static function isValid(string $visitorId, string $consentLevel): bool
    {
        return preg_match(self::VISITOR_ID_PATTERN, $visitorId) === 1
            && in_array($consentLevel, CartVisitorRepository::CONSENT_LEVELS, true);
    }

    /**
     * Keeps the visitor in the cookie for the carts to come, and links the current cart at once
     */
    public function remember(\Context $context, string $visitorId, string $consentLevel): void
    {
        $context->cookie->{self::COOKIE_VISITOR} = $visitorId;
        $context->cookie->{self::COOKIE_CONSENT} = $consentLevel;
        $context->cookie->write();

        if (\Validate::isLoadedObject($context->cart)) {
            $this->repository->save((int) $context->cart->id, $visitorId, $consentLevel);
        }
    }

    /**
     * @return bool false only when the link could not be written
     */
    public function linkCart(\Cookie $cookie, int $idCart): bool
    {
        $visitorId = (string) $cookie->{self::COOKIE_VISITOR};
        $consentLevel = (string) $cookie->{self::COOKIE_CONSENT};
        if ($idCart <= 0 || !self::isValid($visitorId, $consentLevel)) {
            return true;
        }

        return $this->repository->save($idCart, $visitorId, $consentLevel);
    }
}
