<?php
// Load Paystack secret key from config folder
require_once __DIR__ . '/config/config.php';

// Ensure Paystack secret key exists
if (!isset($paystack_secret_key)) {
    die("Paystack Secret Key missing in config.php");
}

// Verify the transaction reference sent by Paystack
if (!isset($_GET['reference'])) {
    die("No transaction reference supplied.");
}

$reference = $_GET['reference'];

// Initialize cURL to verify transaction
$verify_url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verify_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $paystack_secret_key,
    "Cache-Control: no-cache"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// === Handle Verification Failures ===
if (!$result || !isset($result['status'])) {
    die("Unable to verify transaction.");
}

if ($result['status'] !== true || $result['data']['status'] !== 'success') {
    echo "<h2>❌ Payment Failed or Not Verified</h2>";
    echo "<p>Status: " . $result['data']['status'] . "</p>";
    exit;
}

// === Payment Successful ===
$amount = $result['data']['amount'] / 100; // Convert from kobo
$email = $result['data']['customer']['email'];
$ref = $result['data']['reference'];

echo "<h2>✅ Payment Successful</h2>";
echo "<p><strong>Reference:</strong> $ref</p>";
echo "<p><strong>Email:</strong> $email</p>";
echo "<p><strong>Amount Paid:</strong> KES $amount</p>";

// OPTIONAL: Save to database or write to logs
// file_put_contents("payments.txt", "$ref | $email | KES $amount\n", FILE_APPEND);

// Redirect back to your website homepage (optional)
header("refresh:4;url=index.php");
exit;
?>
