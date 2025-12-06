<?php
// Load Paystack keys
require_once __DIR__ . "/config/config.php";

// Read Paystack response
$input = @file_get_contents("php://input");
$event = json_decode($input, true);

// Validate event data
if (!$event || !isset($event['data']['reference'])) {
    http_response_code(400);
    echo "Invalid callback data";
    exit;
}

$reference = $event['data']['reference'];

// Verify transaction with Paystack
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $reference,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $paystackSecret",
        "Cache-Control: no-cache"
    ],
));

$response = curl_exec($curl);
curl_close($curl);

$result = json_decode($response, true);

if ($result['status'] && $result['data']['status'] === "success") {
    http_response_code(200);
    echo "Payment verified successfully";
} else {
    http_response_code(400);
    echo "Payment verification failed";
}
?>
