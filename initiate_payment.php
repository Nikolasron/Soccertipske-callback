<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    sendError('No data received');
}

$data = json_decode($rawInput, true);
if (!$data) {
    sendError('Invalid JSON data');
}

$amount = isset($data['amount']) ? intval($data['amount']) : 0;
$package = isset($data['package']) ? intval($data['package']) : 0;
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$reference = isset($data['reference']) ? $data['reference'] : 'STKE_' . time() . '_' . rand(1000, 9999);

// Validate
if (!$amount) sendError('Amount is required');
if (!$package) sendError('Package is required');
if (!$phone) sendError('Phone number is required');

$packageAmounts = [1 => 50, 2 => 100, 3 => 150];
if (!isset($packageAmounts[$package]) || $packageAmounts[$package] != $amount) {
    sendError('Invalid amount for package');
}

// Format phone
$phone = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone, 0, 1) == '0') {
    $phone = '254' . substr($phone, 1);
}

// Log
file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - Initiate: Amount=$amount, Phone=$phone, Ref=$reference\n", FILE_APPEND);

// Callback URL - MAKE SURE THIS IS CORRECT
$callbackUrl = 'https://soccertipske-callback.onrender.com/callback.php';

// Test if callback URL is accessible before sending payment
$testCallback = curl_init();
curl_setopt($testCallback, CURLOPT_URL, $callbackUrl . '?test=1');
curl_setopt($testCallback, CURLOPT_RETURNTRANSFER, true);
curl_setopt($testCallback, CURLOPT_TIMEOUT, 5);
$testResponse = curl_exec($testCallback);
$testHttpCode = curl_getinfo($testCallback, CURLINFO_HTTP_CODE);
curl_close($testCallback);

if ($testHttpCode != 200) {
    file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - WARNING: Callback URL not accessible! HTTP $testHttpCode\n", FILE_APPEND);
    // Continue anyway, but log the warning
}

// Initialize payment with Paynector
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYNECTOR_API_URL . "/initiate-payment");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYNECTOR_SECRET_KEY,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => $amount,
    'currency' => 'KES',
    'reference' => $reference,
    'phone_number' => $phone,
    'callback_url' => $callbackUrl,
    'redirect_url' => 'https://soccertipske.yourdomain.com/index.php?payment_success=1',
    'description' => 'SoccerTipsKE Premium Package ' . $package,
    'metadata' => [
        'package' => $package,
        'user_phone' => $phone,
        'reference' => $reference
    ]
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

file_put_contents('payment_log.txt', date('Y-m-d H:i:s') . " - Response: HTTP $httpCode, Body: $response\n", FILE_APPEND);

if ($curlError) {
    sendError('CURL Error: ' . $curlError);
}

if ($httpCode == 200 || $httpCode == 201) {
    $result = json_decode($response, true);
    
    if (!$result) {
        sendError('Invalid response from payment gateway');
    }
    
    // Store pending transaction
    $pendingFile = 'pending_' . $reference . '.json';
    file_put_contents($pendingFile, json_encode([
        'reference' => $reference,
        'package' => $package,
        'phone' => $phone,
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'callback_url' => $callbackUrl
    ]));
    
    echo json_encode([
        'status' => 'success', 
        'data' => $result,
        'reference' => $reference,
        'message' => 'STK Push sent',
        'callback_url' => $callbackUrl
    ]);
} else {
    sendError('Payment initiation failed: ' . $response, $httpCode);
}
?>
