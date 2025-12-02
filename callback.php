<?php
require_once "config.php";  // Contains $PAYSTACK_SECRET_KEY

header("Content-Type: text/plain");

// Require Paystack reference
if (!isset($_GET['reference'])) {
    http_response_code(400);
    echo "Missing reference";
    exit;
}

$ref = $_GET['reference'];

// Verify with Paystack API
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $ref,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $PAYSTACK_SECRET_KEY
    ],
]);

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

// Paystack expects a simple OK or FAILED
if ($data && isset($data["data"]["status"]) && $data["data"]["status"] === "success") {
    echo "OK";
} else {
    echo "FAILED";
}
