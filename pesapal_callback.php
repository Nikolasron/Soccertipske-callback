<?php
// ✅ Pesapal Payment Confirmation Callback

$logFile = __DIR__ . '/data/pesapal_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Callback received\n", FILE_APPEND);

$reference = $_GET['pesapal_merchant_reference'] ?? '';
$trackingId = $_GET['pesapal_transaction_tracking_id'] ?? '';
$status = $_GET['pesapal_transaction_status'] ?? '';
$amount = $_GET['amount'] ?? '0';

// If Pesapal sends JSON (some integrations do)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents("php://input");
    if ($raw) {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            $reference = $data['pesapal_merchant_reference'] ?? $reference;
            $trackingId = $data['pesapal_transaction_tracking_id'] ?? $trackingId;
            $status = $data['pesapal_transaction_status'] ?? $status;
            $amount = $data['amount'] ?? $amount;
        }
    }
}

$amount = preg_replace('/[^0-9.]/', '', $amount);
if ($amount == '') $amount = '0';

file_put_contents($logFile, "Ref: $reference | Tracking: $trackingId | Status: $status | Amount: $amount\n", FILE_APPEND);

if (strtolower($status) === 'completed' || strtolower($status) === 'success' || strtolower($status) === 'paid') {
    file_put_contents(__DIR__.'/data/payment_status.txt', 'success');
    file_put_contents(__DIR__.'/data/paid_amount.txt', $amount);

    echo "<h2>✅ Payment Confirmed!</h2>";
    echo "<p>Thank you! Your payment of Ksh {$amount} has been received successfully.</p>";
    echo "<a href='index.php?page=premium' style='background:green;color:white;padding:10px 15px;border-radius:5px;text-decoration:none;'>Unlock Predictions</a>";
} else {
    file_put_contents(__DIR__.'/data/payment_status.txt', 'failed');
    echo "<h2>❌ Payment Not Completed</h2>";
    echo "<p>Status: {$status}. Please retry payment or contact support.</p>";
}
?>
