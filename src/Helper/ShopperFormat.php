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

namespace Dialog\AskDialog\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * How the Shoppers read writes dates and amounts
 *
 * PrestaShop stores dates in the shop's local time, to the second: they leave as ISO 8601
 * with the offset, and a cursor comes back to local time before it reaches SQL.
 */
class ShopperFormat
{
    public static function toLocal(\DateTimeInterface $date): string
    {
        return (new \DateTime('@' . $date->getTimestamp()))->setTimezone(self::timezone())->format('Y-m-d H:i:s');
    }

    public static function toIso(string $localDateTime): string
    {
        return (new \DateTime($localDateTime, self::timezone()))->format(DATE_ATOM);
    }

    /**
     * A decimal string: json_encode prints floats at serialize_precision, often 17 digits
     */
    public static function toAmount(float $amount): string
    {
        return number_format($amount, 6, '.', '');
    }

    private static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }
}
