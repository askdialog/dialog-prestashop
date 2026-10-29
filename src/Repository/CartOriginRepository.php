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
 * The Dialog surface a cart was handed over from (DEC-3660 spike)
 *
 * Keyed by cart: an order keeps its cart's id (ps_orders.id_cart), including when the
 * shopper logs in at checkout, so the origin follows the cart to every order it becomes.
 */
class CartOriginRepository extends AbstractRepository
{
    private const TABLE = 'askdialog_cart_origin';

    /**
     * Record (or replace) the origin of a cart
     *
     * @param int $idCart
     * @param string $origin e.g. dialog_plugin
     * @param string $handoffRef Opaque reference of the handoff on Dialog's side
     *
     * @return bool
     */
    public function save(int $idCart, string $origin, string $handoffRef): bool
    {
        return $this->getDb()->insert(
            self::TABLE,
            [
                'id_cart' => $idCart,
                'origin' => pSQL($origin),
                'handoff_ref' => pSQL($handoffRef),
                'date_add' => date('Y-m-d H:i:s'),
            ],
            false,
            true,
            \Db::REPLACE
        );
    }

    /**
     * @param int $idCart
     *
     * @return array|null ['origin' => string, 'handoff_ref' => string, 'date_add' => string]
     */
    public function findByCart(int $idCart): ?array
    {
        $row = $this->getDb()->getRow(
            'SELECT `origin`, `handoff_ref`, `date_add` FROM `' . $this->getPrefix() . self::TABLE . '`'
            . ' WHERE `id_cart` = ' . $idCart
        );

        return $row ?: null;
    }
}
