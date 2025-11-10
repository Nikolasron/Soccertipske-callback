<?php
// payment-callback.php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Extract payment response parameters
    $paymentStatus = $_GET['payment_status'] ?? '';
    $paymentReference = $_GET['payment_reference'] ?? '';
    $transactionId = $_GET['transaction_id'] ?? '';

    // Check if payment was successful
    if ($paymentStatus == 'SUCCESS') {
        // Payment is successful
        echo "Payment Successful! Reference: " . $paymentReference;
        
        // Optionally, log or update your database here
        // Example: update user status, send confirmation email, etc.
    } else {
        // Payment failed
        echo "Payment Failed! Please try again later or contact support.";
        
        // Log the error or send an alert for manual intervention
    }
} else {
    echo "Invalid request.";
}
?>
