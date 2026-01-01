<!DOCTYPE html>
<html>
<head>
    <title>{translate key="plugins.paymethod.emspubpaddle.paymentSuccess"}</title>
    {if $backLink}
    <meta http-equiv="refresh" content="3;url={$backLink}">
    {/if}
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; background: white; padding: 40px 60px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 450px; width: 100%; }
        h1 { color: #28a745; margin-bottom: 20px; font-size: 24px; }
        .checkmark { font-size: 60px; color: #28a745; line-height: 1; margin-bottom: 20px; }
        p { color: #666; margin: 10px 0; line-height: 1.5; }
        .btn { display: inline-block; margin-top: 25px; padding: 12px 35px; background: #006798; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; transition: background 0.3s; }
        .btn:hover { background: #005580; }
        .redirect-note { font-size: 13px; color: #999; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkmark">✓</div>
        <h1>Payment Successful</h1>
        <p>Your payment was successful for <strong>{$itemName|escape}</strong> ({$currency|escape} {$amount|string_format:"%.2f"}).</p>
        
        {if $backLink}
        <a href="{$backLink}" class="btn">Go to Dashboard</a>
        <p class="redirect-note">{translate key="plugins.paymethod.emspubpaddle.redirecting"}</p>
        {/if}
    </div>
</body>
</html>
