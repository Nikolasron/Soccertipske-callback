<?php
// Load Paystack secret key
require_once __DIR__ . '/config/config.php'; 
// config.php must define:
// define('PAYSTACK_SECRET_KEY', 'sk_live_xxx');

header('Content-Type: text/plain');

// Get reference from GET parameter
if (!isset($_GET['ref'])) {
    die("No payment reference provided.");
}

$ref = $_GET['ref'];
$secretKey = PAYSTACK_SECRET_KEY;

// Verify payment via Paystack API
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/$ref",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $secretKey"
    ],
]);
$response = curl_exec($curl);
$res = json_decode($response, true);
curl_close($curl);

if (!$res['status']) {
    die("Payment verification failed. Response: " . $response);
}

// Payment successful
if ($res['data']['status'] === 'success') {
    // Extract package info from metadata if sent, else default to 0
    $packageId = isset($_GET['package']) ? intval($_GET['package']) : 0;

    // Load unlock tracker
    $unlockTrackerFile = __DIR__ . '/unlock_tracker.json';
    $unlockTracker = file_exists($unlockTrackerFile) ? json_decode(file_get_contents($unlockTrackerFile), true) : [];
    
    $today = date('Y-m-d');
    if(!isset($unlockTracker['date']) || $unlockTracker['date'] != $today){
        $unlockTracker = ['date'=>$today,'unlocked'=>[]];
    }

    // Unlock the package
    if ($packageId > 0) {
        $unlockTracker['unlocked'][$packageId] = true;
        file_put_contents($unlockTrackerFile, json_encode($unlockTracker));
    }

    // Optionally, redirect user back to main page with success message
    header("Location: https://soccertipske.wuaze.com/index.php?package=$packageId&ref=$ref#premium");
    exit;
} else {
    die("Payment failed or not completed yet.");
}
?>
