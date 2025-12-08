<?php
// ================================
// Paystack live keys (SECURED)
// ================================
define('PAYSTACK_SECRET_KEY', 'sk_live_337187d04a2fc4d44d650d6e0b1877d4b0e26119');
define('PAYSTACK_PUBLIC_KEY', 'pk_live_8a9d1734002c31e7a5168107c7f3193b48314f6a');

// Main callback URL
define('CALLBACK_URL', 'https://soccertipske.wuaze.com/index.php');

// ================================
// Get package and reference from GET parameters
// ================================
$package   = isset($_GET['package']) ? intval($_GET['package']) : 0;
$reference = isset($_GET['ref']) ? trim($_GET['ref']) : '';

// Validate input
if (empty($package) || empty($reference)) {
    die("Invalid request: missing package or reference.");
}

// ================================
// Verify payment with Paystack API
// ================================
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . urlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("cURL Error: $err");
}

$result = json_decode($response, true);

if (!$result['status']) {
    die("Payment verification failed: " . ($result['message'] ?? 'Unknown error'));
}

$paymentData = $result['data'];

// Check if payment was successful
if ($paymentData['status'] !== 'success') {
    die("Payment not successful. Status: " . $paymentData['status']);
}

// ================================
// Payment successful, log reference and unlock content
// ================================
$unlockTrackerFile = 'unlock_tracker.json';
$unlockTracker = file_exists($unlockTrackerFile)
    ? json_decode(file_get_contents($unlockTrackerFile), true)
    : [];

// Reset for today if not set
$today = date('Y-m-d');
if (!isset($unlockTracker['date']) || $unlockTracker['date'] != $today) {
    $unlockTracker = ['date' => $today, 'unlocked' => []];
}

// Unlock the selected package
$unlockTracker['unlocked'][$package] = true;

// Save back to file
file_put_contents($unlockTrackerFile, json_encode($unlockTracker));

// Optionally, log the transaction for record keeping
$statusFile = "payment_status.txt";
$logEntry = date('Y-m-d H:i:s') . " | Package: $package | Ref: $reference\n";
file_put_contents($statusFile, $logEntry, FILE_APPEND);

// ================================
// Redirect user back with success
// ================================
$redirectUrl = "https://soccertipske.wuaze.com/index.php?package=$package#premium&success=1";
header("Location: $redirectUrl");
exit;
?>
