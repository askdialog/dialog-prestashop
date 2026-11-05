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
 * How Dialog reaches the customer of a cart: their email, guest flag and phone
 */
class CustomerContactRepository extends AbstractRepository
{
    /**
     * @param int[] $customerIds
     *
     * @return array<int|string, array<string, string>> keyed by id_customer
     */
    public function findCustomers(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        return $this->indexBy($this->selectRows(
            'SELECT `id_customer`, `email`, `is_guest` FROM `' . $this->getPrefix() . 'customer`'
            . ' WHERE `id_customer` IN (' . $this->escapeIds($customerIds) . ')'
        ), 'id_customer');
    }

    /**
     * @param int[] $addressIds
     *
     * @return array<int|string, string> the mobile number, else the landline, keyed by id_address
     */
    public function findPhones(array $addressIds): array
    {
        if ($addressIds === []) {
            return [];
        }

        $rows = $this->selectRows(
            'SELECT `id_address`, `phone`, `phone_mobile` FROM `' . $this->getPrefix() . 'address`'
            . ' WHERE `id_address` IN (' . $this->escapeIds($addressIds) . ')'
        );

        $phones = [];
        foreach ($rows as $row) {
            $mobile = (string) $row['phone_mobile'];
            $phone = $mobile !== '' ? $mobile : (string) $row['phone'];
            if ($phone !== '') {
                $phones[$row['id_address']] = $phone;
            }
        }

        return $phones;
    }
}
