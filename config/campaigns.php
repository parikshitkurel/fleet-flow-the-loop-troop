<?php
/**
 * FleetFlow Campaign Utility
 * Implements Brevo Email Campaigns API V3 via cURL.
 * No external SDK libraries required.
 */

require_once __DIR__ . '/mail.php'; // Reuse API key and settings

/**
 * Creates and schedules an email campaign.
 * 
 * @param array $config Campaign settings (name, subject, htmlContent, listIds, etc.)
 * @return mixed API response or false on failure
 */
function createEmailCampaign($config) {
    if (empty(BREVO_API_KEY)) {
        error_log("Brevo API Key not found.");
        return false;
    }

    $url = "https://api.brevo.com/v3/emailCampaigns";

    // Prepare data based on user configuration
    $data = [
        "name" => $config['name'] ?? "Campaign sent via FleetFlow",
        "subject" => $config['subject'] ?? "My subject",
        "sender" => [
            "name" => $config['sender_name'] ?? MAIL_FROM_NAME,
            "email" => $config['sender_email'] ?? MAIL_FROM_EMAIL
        ],
        "type" => "classic",
        "htmlContent" => $config['htmlContent'] ?? "Hello, this is a campaign from FleetFlow.",
        "recipients" => [
            "listIds" => $config['listIds'] ?? []
        ],
        "scheduledAt" => $config['scheduledAt'] ?? date('c', strtotime('+1 hour'))
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

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    } else {
        error_log("Brevo Campaign Error ($httpCode): $response");
        return false;
    }
}

// Example usage based on user snippet:
/*
try {
    $campaignResult = createEmailCampaign([
        'name' => "Campaign sent via the API",
        'subject' => "My subject",
        'htmlContent' => "Congratulations! You successfully sent this example campaign via the Brevo API.",
        'listIds' => [2, 7],
        'scheduledAt' => "2026-03-01T12:00:00+00:00" // Use ISO 8601
    ]);
    print_r($campaignResult);
} catch (Exception $e) {
    echo 'Error: ', $e->getMessage();
}
*/
