<?php
// ============================================
// Check Payment Status - InfinityFree
// Payment Gateway: Paynectar
// ============================================

require_once 'config.php';

header('Content-Type: application/json');

function sendError($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed');
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    sendError('No data received');
}

$data = json_decode($rawInput, true);
if (!$data) {
    sendError('Invalid JSON');
}

$reference = isset($data['reference']) ? $data['reference'] : '';
if (!$reference) {
    sendError('Reference required');
}

// Check pending file
$pendingFile = 'pending_' . $reference . '.json';
if (file_exists($pendingFile)) {
    $pending = json_decode(file_get_contents($pendingFile), true);
    
    // Check if unlocked in tracker
    if (file_exists('unlock_tracker.json')) {
        $unlockTracker = json_decode(file_get_contents('unlock_tracker.json'), true);
        if (isset($unlockTracker['unlocked'][$pending['package']]) && $unlockTracker['unlocked'][$pending['package']] === true) {
            echo json_encode([
                'status' => 'success',
                'data' => ['status' => 'completed', 'package' => $pending['package']]
            ]);
            exit;
        }
    }
}

// Verify with Paynectar
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
    echo json_encode(['status' => 'success', 'data' => $result]);
} else {
    // If API fails, check if it's been more than 2 minutes since initiation
    if (file_exists($pendingFile)) {
        $pending = json_decode(file_get_contents($pendingFile), true);
        $created = strtotime($pending['created_at']);
        $now = time();
        
        if (($now - $created) > 120) { // 2 minutes timeout
            echo json_encode([
                'status' => 'timeout',
                'message' => 'Payment verification timeout'
            ]);
        } else {
            echo json_encode([
                'status' => 'pending',
                'message' => 'Payment still pending'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Payment not found'
        ]);
    }
}
?>
