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

require_once(dirname(__FILE__).'/classes/tools/config.php');
class MPay extends PaymentModule
{
    public $status_pay = 2;
    public $api_key = null;

    public function __construct()
    {
        $this->name = 'mpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->bootstrap = true;
        $this->author = 'MPay';
        $this->need_instance = 0;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('MPay');
        $this->description = $this->l('MPay is an all-in-one payment processing and e-commerce solution for any cryptocurrency based on Minter');
        $this->confirmUninstall = $this->l('Are you sure you want to delete?');

        $this->status_pay = ConfMP::getConf('MP_STATUS_PAY');
        $this->api_key = ConfMP::getConf('MP_API_KEY');
    }

    public function install()
    {
        $return = parent::install()
            && $this->registerHook('displayPayment')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn')
            && HelperDbMP::loadClass('CartPayRequest')->installDb();
        ConfMP::setConf('MP_STATUS_PAY', 2);
        ConfMP::setConf('MP_API_KEY', '');
        return $return;
    }

    public function uninstall()
    {
        HelperDbMP::loadClass('CartPayRequest')->uninstallDb();
        ConfMP::deleteConf('MP_STATUS_PAY');
        ConfMP::deleteConf('MP_API_KEY');
        return parent::uninstall();
    }

    const PAYMENT_NEW_URL = 'https://mpay.ms/api/payment/new';
    const PAYMENT_GET_URL = 'https://mpay.ms/api/payment/get';

    public function paymentNew()
    {
        $cart = $this->context->cart;
        $currency = Currency::getCurrencyInstance($cart->id_currency);
        $title = sprintf($this->l('Order #%s'), $cart->id);
//        foreach ($cart->getProducts() as $product) {
//            $title .= $product['id_product'].'_'.$product['id_product_attribute'].'('.$product['cart_quantity'].'),';
//        }
//        $title = rtrim($title, ',');

        $total = $cart->getOrderTotal(true);
        if ($currency->iso_code == 'BIP' && $total < 0.001) {
            $total = 0.001;
        }
        $sign = $currency->iso_code;
        $return_url = $this->context->link->getModuleLink(
            'mpay',
            'process'
        );
        $vars = array(
            'title' => $title,
            'currency' => $sign,
            'value' => $total,
            'return_url' => $return_url.(
                strpos($return_url, '?') !== false
                ? '&'
                : '?'
            ).'id=%id%',
            'webhook' => $this->context->link->getModuleLink(
                'mpay',
                'process'
            )
        );
        return $this->sendRequest(self::PAYMENT_NEW_URL, $vars);
    }

    public function paymentGet($id)
    {
        $vars = array(
            'id' => $id,
        );
        return $this->sendRequest(self::PAYMENT_GET_URL, $vars);
    }

    public function sendRequest($method, $vars)
    {
        $api_key = $this->api_key;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$method);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($vars));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
            'Authorization: '.$api_key,
            'Content-Type: application/x-www-form-urlencoded'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec ($ch);
        curl_close ($ch);
        return Tools::jsonDecode($server_output, true);
    }

    public function hookDisplayPayment($params)
    {
        if (!$this->api_key) {
            return null;
        }

        $this->context->smarty->assign(array(
            'mpay' => array(
                'img_dir' => _MODULE_DIR_.$this->name.'/views/img/',
                'validation' => $this->context->link->getModuleLink(
                    $this->name,
                    'validation'
                )
            ),
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookPaymentOptions()
    {
        if (!$this->api_key) {
            return array();
        }

        $new_option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $new_option->setCallToActionText($this->displayName)->setAction(
            $this->context->link->getModuleLink(
                $this->name,
                'validation'
            )
        )->setAdditionalInformation(
            $this->l('MPay - pay with any cryptocurrency based on Minter')
        );

        return array($new_option);
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitConfig')) {
            ConfMP::setConf('MP_STATUS_PAY', Tools::getValue(ConfMP::formatConfName('MP_STATUS_PAY')));
            ConfMP::setConf('MP_API_KEY', Tools::getValue(ConfMP::formatConfName('MP_API_KEY')));
            $this->context->controller->confirmations[] = $this->l('Settings save successfully!');
        }

        return $this->renderForm();
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Module Configuration'),
                    'icon' => 'icon-cog',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Api key'),
                        'name' => ConfMP::formatConfName('MP_API_KEY'),
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Pay status'),
                        'name' => ConfMP::formatConfName('MP_STATUS_PAY'),
                        'required' => true,
                        'options' => array(
                            'query' => OrderState::getOrderStates($this->context->language->id),
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ),
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array(
                ConfMP::formatConfName('MP_API_KEY') => ConfMP::getConf('MP_API_KEY'),
                ConfMP::formatConfName('MP_STATUS_PAY') => ConfMP::getConf('MP_STATUS_PAY')
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }
}
