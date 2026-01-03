{**
 * plugins/paymethod/emspubpaddle/templates/paddleLauncher.tpl
 *
 * Paddle Billing (v2) Checkout Launcher for Ad-hoc Payments (APC, Fees)
 *}
<!DOCTYPE html>
<html>
<head>
    <title>{translate key="common.payment"}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: #f9fafb;
            color: #111827;
        }
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0ABF96;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        h2 { margin-top: 0; color: #111827; font-size: 1.25rem; }
        p { color: #6b7280; line-height: 1.5; }
        
        .error-container {
            display: none;
            background: #fef2f2;
            border: 1px solid #fee2e2;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-top: 1rem;
            color: #991b1b;
            font-size: 0.875rem;
            text-align: left;
        }
    </style>
</head>
<body>

    <div class="card" id="main-card">
        <div class="loader"></div>
        <h2 id="status-title">Preparing Checkout...</h2>
        <p id="status-text">We are redirecting you to our secure payment partner.</p>
        
        <div id="error-box" class="error-container">
            <strong>Error</strong>
            <div id="error-message"></div>
        </div>
    </div>

    <script type="text/javascript">
        function showError(msg) {
            document.querySelector(".loader").style.display = "none";
            document.getElementById("status-title").innerText = "Error";
            document.getElementById("status-text").style.display = "none";
            document.getElementById("error-box").style.display = "block";
            document.getElementById("error-message").innerText = msg;
        }

        window.onload = function() {
            if (typeof Paddle === "undefined") {
                showError("Paddle.js failed to load. Please check your internet connection and disable ad-blockers.");
                return;
            }

            try {
                Paddle.Environment.set("{$paddleEnv}");
                Paddle.Initialize({
                    token: "{$paddleClientToken}",
                    eventCallback: function(data) {
                        if (data.name === "checkout.completed") {
                            window.location.href = "{$successUrl}&transaction_id=" + data.data.transaction_id;
                        }
                        if (data.name === "checkout.closed") {
                            window.location.href = "{$cancelUrl}";
                        }
                        if (data.name === "checkout.error") {
                            showError(data.data.error.message || "An unexpected error occurred during checkout.");
                        }
                    }
                });

                Paddle.Checkout.open({
                    transactionId: "{$transactionId}",
                    settings: {
                        displayMode: "overlay",
                        theme: "light",
                        locale: "en"
                    }
                });
            } catch (e) {
                showError(e.message);
            }
        };
    </script>
</body>
</html>
