<?php
// Load Paystack secret key
require_once __DIR__ . '/config/config.php';

// Track unlock file
$unlockTrackerFile = 'unlock_tracker.json';

// Detect package and reference
$package = isset($_GET['package']) ? intval($_GET['package']) : 0;
$ref     = isset($_GET['ref']) ? $_GET['ref'] : '';

if ($package < 1 || empty($ref)) {
    die("Invalid callback parameters.");
}

// Verify transaction from Paystack
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/$ref",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY
    ],
]);

$response = curl_exec($curl);
curl_close($curl);
$res = json_decode($response, true);

// Load unlock tracker
$unlockTracker = file_exists($unlockTrackerFile)
    ? json_decode(file_get_contents($unlockTrackerFile), true)
    : ['date'=>date('Y-m-d'), 'unlocked'=>[]];

// Payment successful?
if ($res['status'] === true && $res['data']['status'] === 'success') {

    // Unlock selected package
    $unlockTracker['unlocked'][$package] = true;

    file_put_contents($unlockTrackerFile, json_encode($unlockTracker));

    // Redirect back to site
    header("Location: https://soccertipske.onrender.com/index.php?package=$package&ref=$ref#premium");
    exit;

} else {

    // Failed payment
    header("Location: https://soccertipske.onrender.com/index.php?payment=failed#premium");
    exit;
}
