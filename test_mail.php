<?php
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/config/auth.php';

// Diagnostic page for Brevo API
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Brevo API Diagnostic — FleetFlow</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        .diagnostic-box { max-width: 800px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        pre { background: #f8fafc; padding: 16px; border-radius: 4px; overflow-x: auto; font-size: 13px; border: 1px solid #e2e8f0; white-space: pre-wrap; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 12px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body style="background: #f1f5f9; font-family: sans-serif;">

<div class="diagnostic-box">
    <h2>FleetFlow Brevo API Diagnostic</h2>
    <p style="color: #64748b; font-size: 14px;">This tool tests the direct API connection to Brevo (V3) using cURL.</p>
    <hr style="border:0; border-top:1px solid #e2e8f0; margin: 20px 0;">

    <form method="POST">
        <div class="form-group">
            <label>Recipient Email for Test</label>
            <input type="email" name="test_email" class="form-control" value="parikshitkurel@gmail.com" required>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 10px;">Run API Test</button>
    </form>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div style="margin-top: 32px;">
            <h3>Test Results</h3>
            <pre><?php
                $testEmail = $_POST['test_email'];
                testEmailConfig($testEmail);
            ?></pre>
        </div>
    <?php endif; ?>

    <div style="margin-top: 24px; font-size:12px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 16px;">
        <p><strong>Common Troubleshooting:</strong></p>
        <ul style="padding-left: 20px;">
            <li>Ensure <code>parikshitkurel@gmail.com</code> is a <strong>Verified Sender</strong> in your Brevo Dashboard.</li>
            <li>Double-check that your <strong>API Key</strong> is an "API V3" key.</li>
            <li>Check if your server (XAMPP) has the <code>php_curl</code> extension enabled.</li>
        </ul>
    </div>
</div>

</body>
</html>
