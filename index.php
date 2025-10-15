<?php
/*
  SoccerTipsKE Callback Handler
  Receives M-PESA STK Push responses from Safaricom
  Logs all callbacks and updates payment_status.txt dynamically
*/

date_default_timezone_set('Africa/Nairobi');

$dataDir = __DIR__ . "/data/";
$logFile = $dataDir . "callback_log.txt";
$statusFile = $dataDir . "payment_status.txt";

// Make sure /data directory exists
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Get raw callback data from Safaricom
$callbackJSON = file_get_contents('php://input');

// Log raw callback
file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] CALLBACK:\n" . $callbackJSON . "\n\n", FILE_APPEND);

// Decode JSON
$callbackData = json_decode($callbackJSON, true);

// Check for ResultCode (0 = success)
if (isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    $resultCode = $callbackData['Body']['stkCallback']['ResultCode'];

    if ($resultCode == 0) {
        file_put_contents($statusFile, "success");
        file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] ✅ Payment success logged.\n\n", FILE_APPEND);
    } else {
        file_put_contents($statusFile, "failed");
        file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] ❌ Payment failed or canceled.\n\n", FILE_APPEND);
    }
}

// Respond to Safaricom (must always respond with JSON)
header('Content-Type: application/json');
echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Callback received successfully"
]);
?>
