<!DOCTYPE html>
<html>
<head>
    <title>{translate key="plugins.paymethod.emspubpaddle.paymentCancelled"}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
        .container { text-align: center; background: white; padding: 40px 60px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 450px; width: 100%; }
        h1 { color: #dc3545; margin-bottom: 20px; font-size: 24px; }
        .icon { font-size: 60px; color: #dc3545; line-height: 1; margin-bottom: 20px; }
        p { color: #666; margin: 10px 0; line-height: 1.5; }
        .btn { display: inline-block; margin-top: 25px; padding: 12px 35px; background: #006798; color: white; text-decoration: none; border-radius: 4px; font-size: 16px; transition: background 0.3s; }
        .btn:hover { background: #005580; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✕</div>
        <h1>{translate key="plugins.paymethod.emspubpaddle.paymentCancelled"}</h1>
        <p>{translate key="plugins.paymethod.emspubpaddle.purchase.cancelled"}</p>
        
        {if $backLink}
        <a href="{$backLink}" class="btn">Return to Dashboard</a>
        {/if}
    </div>
</body>
</html>
