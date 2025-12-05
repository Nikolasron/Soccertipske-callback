<?php
// Load config.php from root (Structure B)
require_once __DIR__ . '/config.php';

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Read selected package
$package = isset($_GET['package']) ? intval($_GET['package']) : 0;

// Read reference sent by Paystack
$reference = isset($_GET['ref']) ? $_GET['ref'] : "";

// If missing data, stop
if ($package == 0 || empty($reference)) {
    echo "Invalid request. Missing package or reference.";
    exit;
}

// Verify transaction with Paystack
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/$reference",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: Bearer ".PAYSTACK_SECRET_KEY],
]);
$response = curl_exec($curl);
$res = json_decode($response, true);
curl_close($curl);

// File to track unlocks
$unlockTrackerFile = 'unlock_tracker.json';

// Load unlock data
$unlockTracker = file_exists($unlockTrackerFile)
    ? json_decode(file_get_contents($unlockTrackerFile), true)
    : ['date'=>date('Y-m-d'), 'unlocked'=>[]];

// Reset daily unlock if date changed
if (!isset($unlockTracker['date']) || $unlockTracker['date'] != date('Y-m-d')) {
    $unlockTracker = ['date'=>date('Y-m-d'), 'unlocked'=>[]];
}

// If payment success
if ($res['status'] && isset($res['data']['status']) && $res['data']['status'] == 'success') {
    // Unlock selected package
    $unlockTracker['unlocked'][$package] = true;
    file_put_contents($unlockTrackerFile, json_encode($unlockTracker));

    // Redirect back to index with success message
    header("Location: https://soccertipske.onrender.com/index.php?package=$package&ref=$reference#premium");
    exit;
}

// If payment failed
header("Location: https://soccertipske.onrender.com/index.php?package=$package&error=failed#premium");
exit;

?>
