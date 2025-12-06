<?php
// Load Paystack keys from Render environment variables
$paystack_public_key = getenv('PAYSTACK_PUBLIC_KEY');
$paystack_secret_key = getenv('PAYSTACK_SECRET_KEY');

if (!$paystack_public_key || !$paystack_secret_key) {
    die("Paystack keys are not set. Please check environment variables.");
}

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Unlock tracker file
$unlockTrackerFile = 'unlock_tracker.json';

// Load or initialize unlock tracker
$unlockTracker = file_exists($unlockTrackerFile)
    ? json_decode(file_get_contents($unlockTrackerFile), true)
    : ['date' => date('Y-m-d'), 'unlocked' => []];

// Reset unlocks at midnight
$today = date('Y-m-d');
if (!isset($unlockTracker['date']) || $unlockTracker['date'] !== $today) {
    $unlockTracker = ['date' => $today, 'unlocked' => []];
    file_put_contents($unlockTrackerFile, json_encode($unlockTracker));
}

// READ PACKAGE & PAYSTACK REFERENCE
$package = isset($_GET['package']) ? intval($_GET['package']) : 0;
$ref     = isset($_GET['ref']) ? $_GET['ref'] : null;

// If invalid, redirect back
if ($package <= 0 || !$ref) {
    header("Location: https://soccertipske.onrender.com/?error=invalid_callback");
    exit;
}

// Verify transaction with Paystack API
$verifyUrl = "https://api.paystack.co/transaction/verify/" . $ref;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $paystack_secret_key,
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// Check if payment is successful
if ($result && isset($result['data']['status']) && $result['data']['status'] === 'success') {

    // Mark this package as unlocked
    $unlockTracker['unlocked'][$package] = true;

    // Save updated unlock file
    file_put_contents($unlockTrackerFile, json_encode($unlockTracker));

    // Redirect back to main site with success
    header("Location: https://soccertipske.onrender.com/?package=$package&verified=1#premium");
    exit;

} else {
    // Failed verification
    header("Location: https://soccertipske.onrender.com/?package=$package&verified=0#premium");
    exit;
}
?>
