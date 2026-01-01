# EmsPub Paddle Payment Plugin

A custom payment method plugin for OJS 3.4+ that integrates **Paddle Billing v2** for standard OJS transaction types (Submission Fees, APCs, Page Charges, etc.).

## 📌 Overview

This plugin implements the `PaymethodPlugin` interface, allowing it to appear as a payment option throughout the OJS workflow. It is distinct from the `emspubcore` plugin, which handles journal-wide subscription plans.

## ⚙️ Configuration

1.  **Vendor ID**: Your Paddle Vendor ID (found in the Paddle Dashboard).
2.  **API Key**: A valid Paddle API Key (Sandbox or Live).
3.  **Client Token**: Found under **Developer Tools > Authentication** in the Paddle Dashboard.
4.  **Test Mode**: Toggle this to use the Paddle Sandbox environment.

## 🛠 Developer Details

### SDK & Paddle.js
- **Backend**: Uses the Paddle PHP SDK (`paddlehq/paddle-php-sdk`).
- **Frontend**: Uses `Paddle.js v2` for the checkout overlay.

### Checkout Flow
The plugin uses a bridge page (`EmsPubPaddlePaymentForm.php`) to initialize `Paddle.js`. It passes the `QueuedPayment` details to Paddle.
- **Amounts**: All amounts are sent to Paddle in major units (e.g., $99.00).
- **Callbacks**: Upon successful payment, Paddle redirects back to the plugin's `handle` method with a `transaction_id`.

### Status Verification
The plugin performs a server-side check of the transaction status via the Paddle API before fulfilling the payment in OJS:
```php
$transaction = $paddle->transactions->get($transactionId);
if ($transaction->status->value === 'completed') {
    $paymentManager->fulfillQueuedPayment($request, $queuedPayment, $this->getName());
}
```

## ⚠️ Important Notes
- **V2 API**: This plugin is built specifically for **Paddle Billing (v2)**. It is NOT compatible with Paddle Classic.
- **Ad-hoc Prices**: Currently, the plugin utilizes ad-hoc transaction line items to support the dynamic pricing of OJS fees.

## 📄 License
Distributed under the GNU GPL v3.
