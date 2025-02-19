<?php

use SiMixCore\Classes\CustomerAddressSiMixCore;
use SiMixCore\Classes\SiMixCsrfToken;
use SiMixCore\Classes\SiMixHCaptcha;

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class RegistrationControllerCore extends FrontController
{
    /** @var bool */
    public $ssl = true;
    /** @var string */
    public $php_self = 'registration';
    /** @var bool */
    public $auth = false;

    public function checkAccess()
    {
        // If the customer is already logged and he got here by 'accident', we will redirect him away
        if ($this->context->customer->isLogged() && !$this->ajax) {
            $this->redirect_after = $this->authRedirection ? urlencode($this->authRedirection) : 'my-account';
            $this->redirect();
        }

        return parent::checkAccess();
    }

    public function initContent()
    {
        $register_form = $this
            ->makeCustomerForm()
            ->setGuestAllowed(false)
            ->fillWith(Tools::getAllValues())
            ->addCustomZipCodeField()
            ->addCustomCityField()
            ->addCustomCountry()
            ->addCustomAddressField();
        $desiredOrder = [
            'id_gender',
            'firstname',
            'lastname',
            'birthday',
            'custom_address',
            'custom_city',
            'custom_zip',
            'custom_country',
            'company',
            'siret',
            'email',
            'password',
            'psgdpr_psgdpr',
            'ps_dataprivacy_customer_privacy',
            'ps_emailsubscription_newsletter'
        ];
        $register_form->sortFields($desiredOrder);
        $back = rawurldecode(Tools::getValue('back'));

        if (Tools::isSubmit('submitCreate')) {
            $verifyForm = SiMixHCaptcha::verifyHCaptcha(Tools::getValue('h-captcha-response'));
            $csrfToken = Tools::getValue('csrf_token_simix');
            $isValidCsrfToken = SiMixCsrfToken::isValidCsrfToken($csrfToken);

            if (!$isValidCsrfToken) {
                $this->errors[] = 'CSRF Token not valid.';
                return $this->redirectWithNotifications($this->context->link->getPageLink('registration'));
            }

            if (!$verifyForm) {
                $this->errors[] = 'Recaptcha failed, try again.';
                return $this->redirectWithNotifications($this->context->link->getPageLink('registration'));
            }

            $hookResult = array_reduce(
                Hook::exec('actionSubmitAccountBefore', [], null, true),
                function ($carry, $item) {
                    return $carry && $item;
                },
                true
            );

            // If no problem occured in the hook, let's get the user redirected
            if ($hookResult && $register_form->submit() && !$this->ajax) {
                // First option - redirect the customer to desired URL specified in 'back' parameter
                // Before that, we need to check if 'back' is legit URL that is on OUR domain, with the right protocol
                $back = rawurldecode(Tools::getValue('back'));
                if (Tools::urlBelongsToShop($back)) {

                    try {
                        CustomerAddressSiMixCore::setAddressByCustomer(
                            Context::getContext()->customer->id,
                            $register_form->getField('custom_address')->getValue(),
                            $register_form->getField('custom_city')->getValue(),
                            $register_form->getField('custom_zip')->getValue(),
                            $register_form->getField('custom_country')->getValue()
                        );
                    } catch (PrestaShopException $e) {
                    }
                    $this->success[] = 'Thank you for signing up! Please check your email and confirm your address to complete your registration and access SiMiX.';
                    return $this->redirectWithNotifications($back);
                }

                // Second option - we will redirect him to authRedirection if set
                if ($this->authRedirection) {
                    return $this->redirectWithNotifications($this->authRedirection);
                }

                // Third option - we will redirect him to home URL
                return $this->redirectWithNotifications(__PS_BASE_URI__);
            }
        }

        $this->context->smarty->assign([
            'csrf_token_simix' => SiMixCsrfToken::generateToken(),
            'register_form' => $register_form->getProxy(),
            'hook_create_account_top' => Hook::exec('displayCustomerAccountFormTop'),
        ]);
        $this->setTemplate('customer/registration');

        parent::initContent();
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = [
            'title' => $this->trans('Sign up', [], 'Shop.Theme.Customeraccount'),
            'url' => $this->context->link->getPageLink('registration'),
        ];

        return $breadcrumb;
    }

    /**
     * {@inheritdoc}
     */
    public function getCanonicalURL()
    {
        return $this->context->link->getPageLink('registration');
    }
}
