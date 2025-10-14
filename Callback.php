<?php
// === SoccerTipsKE Callback Handler ===
// This receives payment results from Safaricom Daraja and stores them

header("Content-Type: application/json");

// Get the incoming callback data
$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

// For debugging or logging
file_put_contents("callback_log.txt", $callbackJSON . PHP_EOL, FILE_APPEND);

// Check for successful payment result
if (isset($callbackData['Body']['stkCallback']['ResultCode']) && $callbackData['Body']['stkCallback']['ResultCode'] == 0) {
    // Payment was successful
    file_put_contents("payment_status.txt", "success");
} else {
    file_put_contents("payment_status.txt", "failed");
}

// Respond to Safaricom (must return JSON)
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Callback received successfully"]);
?>
