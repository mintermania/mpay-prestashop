<?php
/**
 * 2007-2019 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2012-2019 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

class CartPayRequest extends ObjectModel
{
    /**
     * @var int
     */
    public $id_cart;
    /**
     * @var string
     */
    public $id_request;

    /**
     * @var string
     */
    public $payment_status = -1;

    /**
     * @var string
     */
    public $error;

    public static $definition = array(
        'table' => 'cart_pay_request',
        'primary' => 'id_cart_pay_request',
        'fields' => array(
            'id_cart' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt'
            ),
            'id_request' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString'
            ),
            'payment_status' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt'
            ),
            'error' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString'
            )
        )
    );

    public static function findById($id)
    {
        $id = (int)Db::getInstance()->getValue(
            'SELECT `id_cart_pay_request` FROM '._DB_PREFIX_.'cart_pay_request
            WHERE `id_request` = "'.pSQL($id).'"'
        );
        return ($id ? new self($id) : null);
    }

    public static function findByIdAndCart($id, $id_cart)
    {
        $id = (int)Db::getInstance()->getValue(
            'SELECT `id_cart_pay_request` FROM '._DB_PREFIX_.'cart_pay_request
            WHERE `id_request` = "'.pSQL($id).'" AND `id_cart` = '.(int)$id_cart
        );
        return ($id ? new self($id) : null);
    }

    public static function create($id_cart, $id)
    {
        $object = new self();
        $object->id_cart = (int)$id_cart;
        $object->id_request = (string)$id;
        return $object->add();
    }

    public static function updateById($id, $payment_status, $error)
    {
        $object = self::findById($id);
        if (Validate::isLoadedObject($object)) {
            $object->payment_status = (int)$payment_status;
            $object->error = (string)$error;
            return $object->save();
        }
        return false;
    }
}