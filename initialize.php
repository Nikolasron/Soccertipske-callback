<?php
require_once "config.php";

if (!isset($_GET['package'])) {
    die("Invalid package");
}

$package = intval($_GET['package']);
$amounts = [1=>50, 2=>100, 3=>150];

if (!isset($amounts[$package])) {
    die("Invalid package selected");
}

$amount = $amounts[$package] * 100;

$callback_url = "https://soccertipske-callback.onrender.com/callback.php?package=$package";

$postData = [
    'email' => 'customer@example.com',
    'amount' => $amount,
    'currency' => 'KES',
    'callback_url' => $callback_url,
    'metadata' => ['package'=>$package]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/initialize");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $PAYSTACK_SECRET",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

$response = curl_exec($ch);
$res = json_decode($response, true);
curl_close($ch);

if ($res && isset($res['data']['authorization_url'])) {
    header("Location: " . $res['data']['authorization_url']);
    exit;
} else {
    echo "Failed to initialize payment";
}
