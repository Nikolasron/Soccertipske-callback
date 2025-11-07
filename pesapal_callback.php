<?php
/*
  SoccerTipsKE - Pesapal Callback Handler (IPN Listener)
  -------------------------------------------------------
  This script receives payment notifications from Pesapal.
  When a payment succeeds, it updates local files so your
  website unlocks premium predictions automatically.
*/

date_default_timezone_set('Africa/Nairobi');

$dataDir = __DIR__ . "/data/";
$logFile = $dataDir . "pesapal_log.txt";
$statusFile = $dataDir . "payment_status.txt";
$amountFile = $dataDir . "paid_amount.txt";

// Ensure /data directory exists
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// Get raw request
$rawData = file_get_contents('php://input');

// Log the raw request for debugging
file_put_contents($logFile, "[".date("Y-m-d H:i:s")."] RAW IPN:\n".$rawData."\n\n", FILE_APPEND);

$decoded = json_decode($rawData, true);

if ($decoded && isset($decoded['status'])) {
    $status = strtolower($decoded['status']);
    $amount = isset($decoded['amount']) ? $decoded['amount'] : '0';
    $reference = isset($decoded['pesapal_transaction_tracking_id']) ? $decoded['pesapal_transaction_tracking_id'] : 'N/A';
    $desc = isset($decoded['description']) ? $decoded['description'] : '';

    // Log processed info
    file_put_contents($logFile, "[".date("Y-m-d H:i:s")."] Parsed IPN: status=$status, amount=$amount, ref=$reference\n\n", FILE_APPEND);

    if ($status === 'completed' || $status === 'success' || $status === 'paid') {
        file_put_contents($statusFile, 'success');
        file_put_contents($amountFile, $amount);
    } else {
        file_put_contents($statusFile, 'failed');
    }

    // Respond with success
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Callback received successfully']);
    exit;
}

// Handle simple GET verification (Pesapal may send this)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    file_put_contents($logFile, "[".date("Y-m-d H:i:s")."] GET verification ping received.\n\n", FILE_APPEND);
    echo "OK";
    exit;
}

// Default fail-safe response
http_response_code(400);
echo json_encode(['error' => 'Invalid callback data']);
?>
