<?php
// ============================================
// Payment Initiation - Paynectar STK Push
// For InfinityFree Hosting
// ============================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', 'payment_errors.log');

// Create debug log
function debugLog($message) {
    $log = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents('payment_debug.log', $log, FILE_APPEND);
}

debugLog("=== Payment initiation started ===");

// Load configuration
if (!file_exists('config.php')) {
    debugLog("ERROR: config.php not found");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Configuration file not found']);
    exit;
}

require_once 'config.php';
debugLog("Config loaded successfully");

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Invalid request method - " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

// Function to send error response
function sendError($message, $code = 400) {
    debugLog("ERROR: $message");
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

// Get raw input
$rawInput = file_get_contents('php://input');
debugLog("Raw input: " . $rawInput);

if (empty($rawInput)) {
    sendError('No data received');
}

// Parse JSON
$data = json_decode($rawInput, true);
if (!$data) {
    sendError('Invalid JSON: ' . json_last_error_msg());
}

debugLog("Parsed data: " . json_encode($data));

// Extract parameters
$amount = isset($data['amount']) ? intval($data['amount']) : 0;
$package = isset($data['package']) ? intval($data['package']) : 0;
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$reference = isset($data['reference']) ? $data['reference'] : 'STKE_' . time() . '_' . rand(1000, 9999);

debugLog("Amount: $amount, Package: $package, Phone: $phone, Reference: $reference");

// Validate amount
if (!$amount) sendError('Amount is required');
if ($amount <= 0) sendError('Amount must be greater than 0');

// Validate package
if (!$package) sendError('Package is required');
$validPackages = [1, 2, 3];
if (!in_array($package, $validPackages)) sendError('Invalid package selected');

// Validate phone number
if (!$phone) sendError('Phone number is required');

// Format phone number
$originalPhone = $phone;
$phone = preg_replace('/[^0-9]/', '', $phone);

// Convert to international format
if (substr($phone, 0, 1) == '0') {
    $phone = '254' . substr($phone, 1);
} elseif (substr($phone, 0, 3) == '254') {
    $phone = $phone;
} elseif (substr($phone, 0, 1) == '7') {
    $phone = '254' . $phone;
}

// Validate Kenyan phone number
if (strlen($phone) != 12 || substr($phone, 0, 3) != '254') {
    sendError('Invalid phone number. Use format: 0712345678 or 254712345678');
}

debugLog("Formatted phone: $phone");

// Verify package amounts
$packageAmounts = [
    1 => 50,
    2 => 100,
    3 => 150
];

if ($packageAmounts[$package] != $amount) {
    sendError('Invalid amount for selected package. Expected KSh ' . $packageAmounts[$package]);
}

// ============================================
// TEST MODE - No real payment
// ============================================
if (defined('PAYNECTAR_TEST_MODE') && PAYNECTAR_TEST_MODE === true) {
    debugLog("TEST MODE: Simulating payment");
    
    // Store pending transaction
    $pendingFile = 'pending_' . $reference . '.json';
    $pendingData = [
        'reference' => $reference,
        'package' => $package,
        'phone' => $phone,
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'test_mode' => true
    ];
    
    if (file_put_contents($pendingFile, json_encode($pendingData))) {
        debugLog("Pending file created: $pendingFile");
    } else {
        debugLog("ERROR: Could not create pending file");
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'TEST MODE: STK Push would be sent to ' . $originalPhone,
        'reference' => $reference,
        'test_mode' => true,
        'data' => [
            'status' => 'pending',
            'reference' => $reference,
            'phone' => $originalPhone,
            'amount' => $amount
        ]
    ]);
    exit;
}

// ============================================
// REAL PAYMENT - Initialize with Paynectar API
// ============================================

// Check if API key is configured
if (!defined('PAYNECTAR_SECRET_KEY') || PAYNECTAR_SECRET_KEY == 'YOUR_PAYNECTAR_API_KEY_HERE') {
    sendError('Payment system not configured. Please contact administrator.');
}

// Check if API URL is configured
$apiUrl = defined('PAYNECTAR_API_URL') ? PAYNECTAR_API_URL : 'https://api.paynectar.co.ke/api/v1';
$callbackUrl = defined('PAYNECTAR_CALLBACK_URL') ? PAYNECTAR_CALLBACK_URL : '';
$siteUrl = defined('SITE_URL') ? SITE_URL : '';

debugLog("API URL: $apiUrl");
debugLog("Callback URL: $callbackUrl");

// Prepare payment data
$paymentData = [
    'amount' => $amount,
    'currency' => 'KES',
    'reference' => $reference,
    'phone' => $phone,
    'callback_url' => $callbackUrl,
    'description' => 'SoccerTipsKE Premium Package ' . $package,
    'metadata' => [
        'package' => $package,
        'phone' => $phone,
        'site' => 'SoccerTipsKE'
    ]
];

// Add redirect URL if available
if ($siteUrl) {
    $paymentData['redirect_url'] = $siteUrl . '/index.php?payment_success=1&package=' . $package;
}

debugLog("Payment data: " . json_encode($paymentData));

// Initialize CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl . "/payments/initiate");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYNECTAR_SECRET_KEY,
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Execute CURL
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

debugLog("CURL Response - HTTP Code: $httpCode");
debugLog("CURL Response Body: " . substr($response, 0, 1000));

if ($curlError) {
    debugLog("CURL Error: $curlError");
    sendError('Connection error: ' . $curlError);
}

if ($httpCode == 200 || $httpCode == 201) {
    $result = json_decode($response, true);
    
    if (!$result) {
        debugLog("ERROR: Invalid JSON response");
        sendError('Invalid response from payment gateway');
    }
    
    debugLog("API Response: " . json_encode($result));
    
    // Store pending transaction
    $pendingFile = 'pending_' . $reference . '.json';
    $pendingData = [
        'reference' => $reference,
        'package' => $package,
        'phone' => $phone,
        'amount' => $amount,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'api_response' => $result
    ];
    
    file_put_contents($pendingFile, json_encode($pendingData));
    debugLog("Pending transaction saved: $pendingFile");
    
    // Check if STK Push was successful
    $stkStatus = $result['status'] ?? '';
    $stkMessage = $result['message'] ?? 'STK Push sent';
    
    echo json_encode([
        'status' => 'success',
        'message' => $stkMessage,
        'reference' => $reference,
        'data' => [
            'status' => $stkStatus,
            'reference' => $reference,
            'phone' => $originalPhone,
            'amount' => $amount,
            'api_response' => $result
        ]
    ]);
} else {
    // Try to parse error response
    $errorMsg = 'Payment initiation failed (HTTP ' . $httpCode . ')';
    if ($response) {
        $errorData = json_decode($response, true);
        if ($errorData && isset($errorData['message'])) {
            $errorMsg = $errorData['message'];
        } elseif ($errorData && isset($errorData['error'])) {
            $errorMsg = $errorData['error'];
        }
    }
    sendError($errorMsg);
}
?>
