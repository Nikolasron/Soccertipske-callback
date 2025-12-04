<?php
require_once "config.php";

if (!isset($_GET['package']) || !isset($_GET['reference'])) {
    die("Invalid callback");
}

$package = intval($_GET['package']);
$reference = $_GET['reference'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/$reference");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $PAYSTACK_SECRET"
]);
$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

if (isset($result['data']['status']) && $result['data']['status'] == "success") {

    // Update unlock tracker
    $file = "unlock_tracker.json";
    $data = file_exists($file) ? json_decode(file_get_contents($file), true) : [];

    $data['unlocked'][$package] = true;
    file_put_contents($file, json_encode($data));

    // Redirect user back to InfinityFree
    header("Location: $FRONTEND_URL/index.php?package=$package&unlocked=1#premium");
    exit;

}

echo "Payment failed.";
