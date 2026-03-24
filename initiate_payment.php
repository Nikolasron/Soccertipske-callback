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
if (substr($phone, 0, 3) == '254') {
    $phone = $phone;
}

// Initialize payment with Paynector
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYNECTOR_API_URL . "/initiate-payment");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYNECTOR_SECRET_KEY,
    'Content-Type: application/json',
    'X-Test-Mode: ' . (PAYNECTOR_TEST_MODE ? 'true' : 'false')
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => $amount,
    'currency' => 'KES',
    'reference' => $reference,
    'phone' => $phone,
    'callback_url' => PAYNECTOR_CALLBACK_URL . '?package=' . $package . '&reference=' . $reference,
    'description' => 'SoccerTipsKE Premium Package ' . $package,
    'email' => 'customer@soccertipske.com' // Optional but recommended
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'CURL Error: ' . $curlError
    ]);
} elseif ($httpCode == 200 || $httpCode == 201) {
    $result = json_decode($response, true);
    echo json_encode([
        'status' => 'success', 
        'data' => $result,
        'reference' => $reference
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Payment initiation failed', 
        'code' => $httpCode,
        'response' => $response
    ]);
}
?>
