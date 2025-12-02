<?php
require_once "config.php";

$input = @file_get_contents("php://input");
$event = json_decode($input, true);

$hash = $_SERVER["HTTP_X_PAYSTACK_SIGNATURE"] ?? "";

if (!$hash || !hash_equals(hash_hmac("sha512", $input, $PAYSTACK_SECRET_KEY), $hash)) {
    http_response_code(401);
    exit("Invalid signature");
}

if ($event["event"] === "charge.success") {
    $reference = $event["data"]["reference"];

    // Save to file (used by your main site to unlock)
    file_put_contents("payment_status.txt", $reference . PHP_EOL, FILE_APPEND);
}

http_response_code(200);
echo "OK";
?>
