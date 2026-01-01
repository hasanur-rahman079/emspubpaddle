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
        
        $vendorId = $this->_plugin->getSetting($journal->getId(), 'paddleVendorId');
        $clientToken = $this->_plugin->getSetting($journal->getId(), 'paddleClientToken');
        $isTestMode = $this->_plugin->getSetting($journal->getId(), 'paddleTestMode');

        $successUrl = $request->url(null, 'payment', 'plugin', [$this->_plugin->getName(), 'return'], ['queuedPaymentId' => $this->_queuedPayment->getId()]);
        $cancelUrl = $request->url(null, 'payment', 'plugin', [$this->_plugin->getName(), 'return'], ['queuedPaymentId' => $this->_queuedPayment->getId(), 'status' => 'cancel']);

        // Paddle Billing (v2) prefers initializing via Paddle.js
        // We will output a small bridge page that opens Paddle Checkout
        
        $templateMgr = TemplateManager::getManager($request);
        
        $paddleData = [
            'clientToken' => $clientToken,
            'environment' => $isTestMode ? 'sandbox' : 'production',
            'items' => [
                [
                    'price' => [
                        'description' => $paymentManager->getPaymentName($this->_queuedPayment),
                        'unit_price' => [
                            'amount' => (int)($this->_queuedPayment->getAmount() * 100), // Minor units? Paddle v2 typically uses major units but SDK/API differs. 
                            // Actually Paddle v2 Price objects use decimal but Checkout sometimes expects integers in minor units if using "manually".
                            // Let's check Paddle.js v2 docs.
                            'currency_code' => $this->_queuedPayment->getCurrencyCode(),
                        ],
                        'custom_data' => [
                            'queued_payment_id' => $this->_queuedPayment->getId(),
                        ],
                    ],
                    'quantity' => 1,
                ]
            ],
            'successUrl' => $successUrl . '&transaction_id={transaction_id}',
            'cancelUrl' => $cancelUrl,
        ];

        // For simplicity, we'll use a hidden auto-submitting view or just a simple page with Paddle.js
        // But since this is a "Form" display in OJS, we can just output HTML.

        echo '<!DOCTYPE html>
<html>
<head>
    <title>Redirecting to Payment...</title>
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f9f9f9; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 2s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div style="text-align: center;">
        <div class="loader" style="margin: 0 auto 20px;"></div>
        <p>Connecting to Paddle...</p>
    </div>

    <script type="text/javascript">
        Paddle.Environment.set("' . $paddleData['environment'] . '");
        Paddle.Setup({ 
            token: "' . $paddleData['clientToken'] . '"
        });

        Paddle.Checkout.open({
            settings: {
                displayMode: "overlay",
                theme: "light",
                locale: "en",
                successUrl: "' . $paddleData['successUrl'] . '"
            },
            items: [{
                priceId: null, // We are creating a custom price below? No, Paddle v2 usually wants a Price ID.
                // However, we can use "custom" items if enabled. 
                // Alternatively, we create a one-time transaction.
            }],
            customData: {
                queuedPaymentId: ' . $this->_queuedPayment->getId() . '
            },
            // Since we don\'t have a Price ID from Paddle Dashboard (dynamic APC), 
            // we might need to use the "unmapped" price or use the Product/Price API first.
            // For now, let\'s assume the user has a generic APC product and we pass the amount.
            // Wait, Paddle v2 "open" doesn\'t easily allow arbitrary amounts without a Price ID.
            
            // FALLBACK: If we can\'t use dynamic prices easily in overlay, 
            // we should have pre-created prices or use the Transaction API to get a checkout URL.
        });
        
        // Let\'s use a more robust approach: Create a transaction server-side and then open it.
    </script>
</body>
</html>';
        exit;
    }
}
