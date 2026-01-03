<?php

/**
 * @file plugins/paymethod/emspubpaddle/EmsPubPaddlePlugin.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class EmsPubPaddlePlugin
 *
 * @ingroup plugins_paymethod_emspubpaddle
 *
 * @brief Paddle payment plugin class for EMS.pub
 */

namespace APP\plugins\paymethod\emspubpaddle;

use APP\core\Application;
use APP\core\Request;
use APP\template\TemplateManager;
use Illuminate\Support\Collection;
use PKP\components\forms\context\PKPPaymentSettingsForm;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\PaymethodPlugin;
use Paddle\SDK\Environment;
use Paddle\SDK\Client as PaddleClient;
use Paddle\SDK\Options as PaddleOptions;

require_once(dirname(__FILE__) . '/vendor/autoload.php');

class EmsPubPaddlePlugin extends PaymethodPlugin
{
    /**
     * @see Plugin::getName
     */
    public function getName()
    {
        return 'emspubpaddle';
    }

    /**
     * @see Plugin::getDisplayName
     */
    public function getDisplayName()
    {
        return __('plugins.paymethod.emspubpaddle.displayName');
    }

    /**
     * @see Plugin::getDescription
     */
    public function getDescription()
    {
        return __('plugins.paymethod.emspubpaddle.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        $this->addLocaleData();
        Hook::add('Form::config::before', $this->addSettings(...));
        return true;
    }

    /**
     * Add settings to the payments form
     *
     * @param string $hookName
     * @param \PKP\components\forms\FormComponent $form
     */
    public function addSettings($hookName, $form)
    {
        if ($form->id !== PKPPaymentSettingsForm::FORM_PAYMENT_SETTINGS) {
            return;
        }

        $context = Application::get()->getRequest()->getContext();
        if (!$context) {
            return;
        }

        $form->addGroup([
            'id' => 'emspubpaddlepayment',
            'label' => __('plugins.paymethod.emspubpaddle.displayName'),
            'showWhen' => 'paymentsEnabled',
        ])
            ->addField(new \PKP\components\forms\FieldOptions('paddleTestMode', [
                'label' => __('plugins.paymethod.emspubpaddle.settings.testMode'),
                'options' => [
                    ['value' => true, 'label' => __('common.enable')]
                ],
                'value' => (bool) $this->getSetting($context->getId(), 'paddleTestMode'),
                'groupId' => 'emspubpaddlepayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('paddleVendorId', [
                'label' => __('plugins.paymethod.emspubpaddle.settings.vendorId'),
                'value' => $this->getSetting($context->getId(), 'paddleVendorId'),
                'groupId' => 'emspubpaddlepayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('paddleApiKey', [
                'label' => __('plugins.paymethod.emspubpaddle.settings.apiKey'),
                'value' => $this->getSetting($context->getId(), 'paddleApiKey'),
                'groupId' => 'emspubpaddlepayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('paddleClientToken', [
                'label' => __('plugins.paymethod.emspubpaddle.settings.clientToken'),
                'value' => $this->getSetting($context->getId(), 'paddleClientToken'),
                'groupId' => 'emspubpaddlepayment',
            ]));

        return;
    }

    /**
     * @copydoc PaymethodPlugin::saveSettings
     */
    public function saveSettings(string $hookname, array $args)
    {
        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $request = $args[1]; /** @var Request $request */
        $updatedSettings = $args[3]; /** @var Collection $updatedSettings */

        $allParams = $illuminateRequest->input();
        $saveParams = [];
        foreach ($allParams as $param => $val) {
            switch ($param) {
                case 'paddleVendorId':
                case 'paddleApiKey':
                case 'paddleClientToken':
                    $saveParams[$param] = (string) $val;
                    break;
                case 'paddleTestMode':
                    $saveParams[$param] = $val === 'true';
                    break;
            }
        }
        $contextId = $request->getContext()->getId();
        foreach ($saveParams as $param => $val) {
            $this->updateSetting($contextId, $param, $val);
            $updatedSettings->put($param, $val);
        }
    }

    /**
     * @copydoc PaymethodPlugin::getPaymentForm()
     */
    public function getPaymentForm($context, $queuedPayment)
    {
        return new EmsPubPaddlePaymentForm($this, $queuedPayment);
    }

    /**
     * @copydoc PaymethodPlugin::isConfigured
     */
    public function isConfigured($context)
    {
        if (!$context) {
            return false;
        }
        if ($this->getSetting($context->getId(), 'paddleApiKey') == '') {
            return false;
        }
        return true;
    }

    /**
     * Handle return from Paddle
     */
    public function handle($args, $request)
    {
        if (Config::getVar('general', 'sandbox', false)) {
            error_log('Application is set to sandbox mode and no payment will be done via emspubpaddle');
            return;
        }

        $journal = $request->getJournal();
        $queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO');
        
        try {
            $queuedPaymentId = $request->getUserVar('queuedPaymentId');
            $queuedPayment = $queuedPaymentDao->getById($queuedPaymentId);
            
            if (!$queuedPayment) {
                throw new \Exception("Invalid queued payment ID {$queuedPaymentId}!");
            }

            $paymentManager = Application::get()->getPaymentManager($journal);
            $itemName = $paymentManager->getPaymentName($queuedPayment);
            $amount = $queuedPayment->getAmount();
            $currency = $queuedPayment->getCurrencyCode();
            $dashboardUrl = $request->url(null, 'dashboard');

            // Handle explicit cancel
            if ($request->getUserVar('status') === 'cancel') {
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'backLink' => $dashboardUrl,
                    'itemName' => $itemName,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);
                $templateMgr->display($this->getTemplateResource('paymentCancel.tpl'));
                return;
            }

            // Paddle Checkout usually redirects back with a checkout_id or transaction_id
            $transactionId = $request->getUserVar('transaction_id');
            if (!$transactionId) {
                 // Try webhook or other param?
                 // For now, let's assume we get transaction_id back if configured in success_url
                 throw new \Exception("Missing transaction_id return parameter.");
            }

            // Initialize SDK
            $apiKey = $this->getSetting($journal->getId(), 'paddleApiKey');
            $environment = $this->getSetting($journal->getId(), 'paddleTestMode') ? Environment::SANDBOX : Environment::PRODUCTION;
            
            $paddle = new PaddleClient($apiKey, new PaddleOptions($environment));
            
            // Verify transaction
            $response = $paddle->getRaw("/transactions/{$transactionId}");
            $responseData = json_decode($response->getBody()->getContents(), true);
            $transaction = $responseData['data'] ?? null;

            if ($transaction && ($transaction['status'] === 'completed' || $transaction['status'] === 'paid')) {
                $paymentManager->fulfillQueuedPayment($request, $queuedPayment, $this->getName());
                
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assign([
                    'backLink' => $dashboardUrl,
                    'itemName' => $itemName,
                    'amount' => $amount,
                    'currency' => $currency,
                ]);
                $templateMgr->display($this->getTemplateResource('paymentSuccess.tpl'));
                return;
            } else {
                 throw new \Exception('Payment status is not completed. Status: ' . ($transaction['status'] ?? 'unknown'));
            }

        } catch (\Exception $e) {
            error_log('Paddle handle exception: ' . $e->getMessage());
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign('message', 'plugins.paymethod.emspubpaddle.error');
            $templateMgr->display('frontend/pages/message.tpl');
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\paymethod\emspubpaddle\EmsPubPaddlePlugin', '\EmsPubPaddlePlugin');
}
