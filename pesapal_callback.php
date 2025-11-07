<?php
// ✅ pesapal_callback.php — hosted on Render.com

// Log raw callback data for debugging
$logFile = __DIR__ . '/callback_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Callback received\n", FILE_APPEND);

// Check for Pesapal parameters
if (isset($_GET['pesapal_transaction_tracking_id']) && isset($_GET['pesapal_merchant_reference'])) {

    $trackingId = $_GET['pesapal_transaction_tracking_id'];
    $reference  = $_GET['pesapal_merchant_reference'];

    // 🔹 Normally you’d verify the transaction with Pesapal API here
    // For testing, we’ll simulate different payment amounts by reference

    // Example: reference might include plan number (like plan1, plan2, plan3)
    if (stripos($reference, 'plan1') !== false) {
        $amount = 50;
    } elseif (stripos($reference, 'plan2') !== false) {
        $amount = 100;
    } elseif (stripos($reference, 'plan3') !== false) {
        $amount = 150;
    } else {
        $amount = 50; // Default for unknown reference
    }

    $status = 'success';

    // 🔗 Update your Wuaze main site
    $wuaze_url = "https://soccertipske.wuaze.com/?status={$status}&amount={$amount}";
    $response = @file_get_contents($wuaze_url);

    // 🟢 Log result
    file_put_contents($logFile, "Tracking ID: $trackingId | Reference: $reference | Amount: $amount | Status: $status\n", FILE_APPEND);

    echo "✅ Payment update sent to Wuaze successfully. Amount: Ksh {$amount}";
} else {
    echo "⚠️ Invalid callback parameters.";
}
?>
