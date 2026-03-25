<?php
// callback.php - Deploy this to Render.com
// URL: https://soccertipske-callback.onrender.com/callback.php

// Log everything for debugging
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'input' => file_get_contents('php://input')
];

// Write to log file
file_put_contents('callback_log.txt', json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Always return a success response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Callback received',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
