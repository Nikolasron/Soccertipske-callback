<?php
// pesapal_callback.php — hosted on Render.com

if (isset($_GET['pesapal_transaction_tracking_id']) && isset($_GET['pesapal_merchant_reference'])) {
    // In real usage, confirm payment from Pesapal API here
    // For now, assume payment was successful and amount = 50
    $amount = 50;
    $status = 'success';

    // Wuaze main site URL — update if needed
    $wuaze_url = "https://soccertipske.wuaze.com/?status={$status}&amount={$amount}";

    // Send update to Wuaze
    $response = file_get_contents($wuaze_url);

    // Respond to Pesapal
    echo "Payment update sent to Wuaze successfully.";
} else {
    echo "Invalid callback parameters.";
}
?>
