<?php
/**
 * FleetFlow Mail Utility
 * Optimized for Brevo API V3 (Transactional) via cURL.
 * No external libraries required.
 */

// ─── MAIL SYSTEM SETTINGS ───────────────────────────────────
define('MAIL_DEBUG', true); // Set to false in production

// ─── CREDENTIALS ────────────────────────────────────────────
define('BREVO_API_KEY',   'REPLACE_WITH_YOUR_BREVO_SMTP_API_KEY'); // Get from ReadME file
define('MAIL_FROM_EMAIL', 'parikshitkurel@gmail.com');
define('MAIL_FROM_NAME',  'FleetFlow System');

/**
 * Sends an email using Brevo API V3 via cURL.
 * Replacement for PHPMailer to avoid library dependencies and SMTP issues.
 */
function sendOTPEmail($toEmail, $otp) {
    if (empty(BREVO_API_KEY) || BREVO_API_KEY === 'REPLACE_WITH_YOUR_BREVO_SMTP_API_KEY') {
        error_log("Brevo API Key is not configured.");
        return false;
    }

    $url = "https://api.brevo.com/v3/smtp/email";
    
    $subject = "Your FleetFlow Verification Code: $otp";
    $htmlContent = "
    <div style='font-family: sans-serif; background: #f5f7fb; padding: 40px; color: #1e293b;'>
        <div style='max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
            <div style='background: #714B67; padding: 24px; text-align: center;'>
                <span style='font-size: 32px;'>🚛</span>
                <h2 style='color: #ffffff; margin: 8px 0 0 0; font-size: 24px;'>FleetFlow</h2>
            </div>
            <div style='padding: 32px;'>
                <h3 style='margin-top: 0; color: #1e293b;'>Verification Code</h3>
                <p style='color: #64748b; font-size: 15px; line-height: 1.6;'>
                    A request was made to verify your account. Please use the following code to continue:
                </p>
                <div style='background: #f8fafc; border: 1px dashed #714B67; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0;'>
                    <span style='font-size: 32px; font-weight: 800; letter-spacing: 12px; color: #714B67; display: block;'>$otp</span>
                </div>
                <p style='color: #64748b; font-size: 13px; text-align: center;'>
                    This code will expire in <strong>10 minutes</strong>.
                </p>
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 32px 0;'>
                <p style='font-size: 12px; color: #94a3b8; line-height: 1.5;'>
                    If you did not request this code, you can safely ignore this email. 
                    Security for your fleet operations is our top priority.
                </p>
            </div>
            <div style='background: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8;'>
                &copy; " . date('Y') . " FleetFlow & Logistics Management
            </div>
        </div>
    </div>";

    $data = [
        "sender" => ["name" => MAIL_FROM_NAME, "email" => MAIL_FROM_EMAIL],
        "to" => [["email" => $toEmail]],
        "subject" => $subject,
        "htmlContent" => $htmlContent
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . BREVO_API_KEY,
        'content-type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        error_log("Brevo API Error ($httpCode): $response $curlError");
        if (MAIL_DEBUG) {
            echo "<!-- API Debug: Code $httpCode, Response $response -->";
        }
        return false;
    }
}

/**
 * Diagnostic test for API configuration.
 */
function testEmailConfig($testEmail) {
    if (empty(BREVO_API_KEY)) {
        echo "API Key missing.";
        return false;
    }

    echo "Attempting to send API V3 Test to: $testEmail...\n";
    
    $result = sendOTPEmail($testEmail, "TEST_API_123");
    
    if ($result) {
        echo "[SUCCESS] API connection established. Email accepted by Brevo.";
        return true;
    } else {
        echo "[FAILED] API request failed. Check server logs.";
        return false;
    }
}
