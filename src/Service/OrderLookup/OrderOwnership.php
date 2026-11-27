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

namespace Dialog\AskDialog\Service\OrderLookup;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Whether the email a shopper gave is their order's
 *
 * Every order of a reference is compared, and an unknown reference against a stand-in, so
 * that neither the answer nor its time tells an unknown reference from another email.
 */
class OrderOwnership
{
    private const STAND_IN = 'no-order@askdialog.invalid';

    /**
     * @param array<int, string|null> $orderEmails the customer email of each order
     */
    public static function matches(array $orderEmails, string $email): bool
    {
        $given = self::normalize($email);
        $candidates = $orderEmails === [] ? [self::STAND_IN] : $orderEmails;

        $matches = $orderEmails !== [];
        foreach ($candidates as $orderEmail) {
            $matches = hash_equals(self::normalize((string) $orderEmail), $given) && $matches;
        }

        return $matches;
    }

    private static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
