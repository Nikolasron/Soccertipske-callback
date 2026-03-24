<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$amount = $data['amount'] ?? 0;
$package = $data['package'] ?? 0;
$phone = $data['phone'] ?? '';
$reference = $data['reference'] ?? 'STKE_' . time() . '_' . rand(1000, 9999);

// Validate input
if (!$amount || !$package || !$phone) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

// Map packages to amounts (for verification)
$packageAmounts = [1 => 50, 2 => 100, 3 => 150];
if ($packageAmounts[$package] != $amount) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid amount for package']);
    exit;
}

// Format phone number for Paynector
$phone = preg_replace('/[^0-9]/', '', $phone);
if (substr($phone, 0, 1) == '0') {
    $phone = '254' . substr($phone, 1);
}
if (substr($phone, 0, 3) == '254' && strlen($phone) == 12) {
    $phone = $phone;
}

// Log the request for debugging
$logEntry = date('Y-m-d H:i:s') . " - Initiate Payment - Amount: $amount, Phone: $phone, Ref: $reference\n";
file_put_contents('payment_log.txt', $logEntry, FILE_APPEND);

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
    'callback_url' => PAYNECTOR_CALLBACK_URL . '?package=' . $package . '&reference=' . $reference,
    'description' => 'SoccerTipsKE Premium Package ' . $package,
    'email' => 'customer@soccertipske.com',
    'payment_method' => 'mpesa' // Specify M-Pesa as payment method
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Log the response for debugging
$logEntry = date('Y-m-d H:i:s') . " - Response Code: $httpCode, Response: $response\n";
file_put_contents('payment_log.txt', $logEntry, FILE_APPEND);

curl_close($ch);

if ($curlError) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Connection error: ' . $curlError,
        'debug' => $curlError
    ]);
} elseif ($httpCode == 200 || $httpCode == 201) {
    $result = json_decode($response, true);
    
    // Log successful initiation
    $logEntry = date('Y-m-d H:i:s') . " - Payment initiated successfully: " . json_encode($result) . "\n";
    file_put_contents('payment_log.txt', $logEntry, FILE_APPEND);
    
    echo json_encode([
        'status' => 'success', 
        'data' => $result,
        'reference' => $reference,
        'phone' => $phone,
        'amount' => $amount
    ]);
} else {
    // Log error
    $logEntry = date('Y-m-d H:i:s') . " - Error: HTTP $httpCode, Response: $response\n";
    file_put_contents('payment_log.txt', $logEntry, FILE_APPEND);
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'Payment initiation failed',
        'code' => $httpCode,
        'response' => $response,
        'debug' => 'Check payment_log.txt for details'
    ]);
}
?>
