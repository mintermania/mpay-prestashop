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

class mpayprocessModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $id = Tools::getValue('id');

        if (!$id) {
            die('Invalid request data!');
        }

        $pay_request = CartPayRequest::findByIdAndCart($id, $this->context->cart->id);

        if ($pay_request && Validate::isLoadedObject($pay_request)) {
            $response = $this->module->paymentGet($id);

            if ($response['status'] == 1) {
                $cart = $this->context->cart;
                $customer = $this->context->customer;
                $this->module->validateOrder(
                    (int)$cart->id,
                    (int)$this->module->status_pay,
                    $cart->getOrderTotal(),
                    $this->module->displayName,
                    null,
                    array(),
                    (int)$this->context->currency->id,
                    false,
                    $customer->secure_key
                );

            } else {
                die(Tools::jsonEncode($response['error']));
            }

            $pay_request->payment_status = $response['payment_status'];
            if (isset($response['error'])) {
                $pay_request->error = Tools::jsonEncode($response['error']);
            }
            $pay_request->save();

            if ($this->module->currentOrder) {
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart='.(int)$cart->id
                    .'&id_module='.(int)$this->module->id.'&id_order='
                    .$this->module->currentOrder.'&key='.$customer->secure_key
                );
                exit;
            }
        }

        $pay_request_find = CartPayRequest::findById($id);
        if ($pay_request_find) {
            if ($pay_request_find && $pay_request_find->payment_status == 0) {
                $customer = $this->context->customer;
                $id_order = (int)Db::getInstance()->getValue(
                    'SELECT id_order FROM '._DB_PREFIX_.'orders
                    WHERE id_cart = '.(int)$pay_request_find->id_cart
                );

                if ($id_order) {
                    Tools::redirect(
                        'index.php?controller=order-confirmation&id_cart='.(int)$pay_request_find->id_cart
                        .'&id_module='.(int)$this->module->id.'&id_order='
                        .(int)$id_order.'&key='.$customer->secure_key
                    );
                }
            }
        }
        Tools::redirect($this->context->link->getPageLink('index'));
        exit;
    }
}