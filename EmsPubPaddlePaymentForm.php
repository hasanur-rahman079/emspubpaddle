<?php

/**
 * @file plugins/paymethod/emspubpaddle/EmsPubPaddlePaymentForm.php
 *
 * Copyright (c) 2024 EmsPub
 * Distributed under the GNU GPL v3.
 *
 * @class EmsPubPaddlePaymentForm
 *
 * Form for Paddle-based payments for EMS.pub.
 */

namespace APP\plugins\paymethod\emspubpaddle;

use APP\core\Application;
use APP\core\Request;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\form\Form;
use PKP\payment\QueuedPayment;

require_once(dirname(__FILE__) . '/vendor/autoload.php');

class EmsPubPaddlePaymentForm extends Form
{
    /** @var EmsPubPaddlePlugin */
    public $_plugin;

    /** @var QueuedPayment */
    public $_queuedPayment;

    /**
     * @param EmsPubPaddlePlugin $plugin
     * @param QueuedPayment $queuedPayment
     */
    public function __construct($plugin, $queuedPayment)
    {
        $this->_plugin = $plugin;
        $this->_queuedPayment = $queuedPayment;
        parent::__construct(null);
    }

    /**
     * @copydoc Form::display()
     */
    public function display($request = null, $template = null)
    {
        if (Config::getVar('general', 'sandbox', false)) {
            TemplateManager::getManager($request)
                ->assign('message', 'common.sandbox')
                ->display('frontend/pages/message.tpl');
            return;
        }

        $journal = $request->getJournal();
        $paymentManager = Application::get()->getPaymentManager($journal);
        
        $apiKey = $this->_plugin->getSetting($journal->getId(), 'paddleApiKey');
        $clientToken = $this->_plugin->getSetting($journal->getId(), 'paddleClientToken');
        $isTestMode = $this->_plugin->getSetting($journal->getId(), 'paddleTestMode');

        if (!$apiKey || !$clientToken) {
            echo 'Paddle gateway not configured.';
            exit;
        }

        try {
            // Initialize SDK
            $environment = $isTestMode ? \Paddle\SDK\Environment::SANDBOX : \Paddle\SDK\Environment::PRODUCTION;
            $paddle = new \Paddle\SDK\Client($apiKey, new \Paddle\SDK\Options($environment));

            // Create an ad-hoc Transaction for the APC/Fee
            // Consistent with emspubcore: use cents and postRaw
            $amountCents = (int) round($this->_queuedPayment->getAmount() * 100);
            $itemName = $paymentManager->getPaymentName($this->_queuedPayment);

            // Fetch journal-specific Product ID for APC from emspubcore plugin settings
            // Try to load emspubcore plugin (may be site-wide)
            \PKP\plugins\PluginRegistry::loadCategory('generic', true, 0);
            $emspubcorePlugin = \PKP\plugins\PluginRegistry::getPlugin('generic', 'emspubcore');
            $productId = null;
            
            if ($emspubcorePlugin) {
                $productId = $emspubcorePlugin->getSetting($journal->getId(), 'paddleApcProductId');
            }
            
            // Fallback: Read directly from plugin_settings table if plugin not found
            if (!$productId) {
                $pluginSettingsDao = \PKP\db\DAORegistry::getDAO('PluginSettingsDAO');
                $productId = $pluginSettingsDao->getSetting($journal->getId(), 'emspubcoreplugin', 'paddleApcProductId');
            }
            
            error_log('Paddle APC: productId from settings: ' . ($productId ?: 'null'));

            if (!$productId) {
                error_log('Paddle Transaction Creation Failed for ' . $itemName . ': No Product ID found for APC in journal settings.');
                throw new \Exception('Paddle gateway not fully configured for this journal (missing Product ID for APC). Please set the Product ID in Payment Types settings.');
            }

            $response = $paddle->postRaw('/transactions', [
                'items' => [
                    [
                        'price' => [
                            'description' => $itemName,
                            'name' => 'Journal Payment',
                            'tax_mode' => 'external',
                            'unit_price' => [
                                'amount' => (string) $amountCents,
                                'currency_code' => $this->_queuedPayment->getCurrencyCode(),
                            ],
                            'product_id' => $productId,
                            'quantity' => [
                                'minimum' => 1,
                                'maximum' => 1
                            ],
                            'custom_data' => [
                                'queued_payment_id' => (string)$this->_queuedPayment->getId(),
                                'type' => 'ad_hoc'
                            ]
                        ],
                        'quantity' => 1
                    ]
                ],
                'custom_data' => [
                    'queued_payment_id' => (string)$this->_queuedPayment->getId(),
                    'journal_id' => (string)$journal->getId(),
                    'type' => 'apc_payment'
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $transactionId = $responseData['data']['id'] ?? null;

            if (!$transactionId) {
                error_log('Paddle Transaction Creation Failed for ' . $itemName . ': ' . print_r($responseData, true));
                throw new \Exception($responseData['error']['detail'] ?? 'Failed to create transaction');
            }

            // Redirect to My Invoices page after successful payment
            // Include queuedPaymentId as a query param so Paddle appends transaction_id with & correctly
            $successUrl = $request->url(null, 'emspubcore', 'pendingPayments', null, ['paid' => $this->_queuedPayment->getId()]);
            $cancelUrl = $request->url(null, 'emspubcore', 'pendingPayments', null, ['cancelled' => 1]);

            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'paddleEnv' => $isTestMode ? 'sandbox' : 'production',
                'paddleClientToken' => $clientToken,
                'transactionId' => $transactionId,
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
            ]);

            $templateMgr->display($this->_plugin->getTemplateResource('paddleLauncher.tpl'));
            exit;

        } catch (\Exception $e) {
            error_log('Paddle Transaction Creation Error: ' . $e->getMessage());
            echo 'Error connecting to payment gateway: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }
}
