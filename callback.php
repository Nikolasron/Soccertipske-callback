<?php
// ============================================
// Paynectar Callback Handler - Render.com
// URL: https://soccertipske-callback.onrender.com/callback.php
// ============================================

// Load configuration
require_once 'config.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'php-error.log');

// Log all incoming data for debugging
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'input' => file_get_contents('php://input')
];

file_put_contents('callback_log.txt', json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Always return success to Paynectar
header('Content-Type: application/json');

// Get payment data
$reference = $_POST['reference'] ?? $_GET['reference'] ?? '';
$status = $_POST['status'] ?? $_GET['status'] ?? '';
$package = $_POST['metadata']['package'] ?? $_GET['package'] ?? '';

if (!$reference) {
    echo json_encode(['status' => 'error', 'message' => 'No reference provided']);
    exit;
}

// Verify the payment with Paynectar API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, PAYNECTAR_API_URL . "/payments/verify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . PAYNECTAR_SECRET_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'reference' => $reference
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    $result = json_decode($response, true);
    
    if (isset($result['status']) && ($result['status'] == 'success' || $result['status'] == 'completed')) {
        // Payment successful - Call your main site to unlock content
        $unlockUrl = SITE_URL . "/unlock.php?reference=" . urlencode($reference) . "&package=" . $package . "&status=success";
        
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $unlockUrl);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
        curl_exec($ch2);
        curl_close($ch2);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Payment verified and unlocked',
            'reference' => $reference
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Payment not completed',
            'reference' => $reference
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Verification failed',
        'reference' => $reference
    ]);
}
?>
