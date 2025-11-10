<?php
// This is the file that checks the payment status and updates the user's payment status accordingly

// Path to the status and payment amount files
$statusFile = __DIR__ . '/data/payment_status.txt';
$amountFile = __DIR__ . '/data/paid_amount.txt';

// Function to check the payment status
function checkPaymentStatus($merchantReference, $transactionTrackingId) {
    // Your Pesapal credentials
    $consumer_key = 'fscynXgvnF1MD8ZrosN/zegFf1EPBV5q';
    $consumer_secret = 'Xc6vsXcK8hoAg80eQkdaAP6sHVo=';

    // Pesapal API endpoint for checking payment status
    $pesapal_endpoint = "https://www.pesapal.com/API/TransactionStatus";

    // Set up the request headers
    $headers = [
        'Authorization: Basic ' . base64_encode($consumer_key . ':' . $consumer_secret),
        'Content-Type: application/json'
    ];

    // Prepare the request body
    $request_data = [
        'merchant_reference' => $merchantReference,
        'transaction_tracking_id' => $transactionTrackingId
    ];

    // Set up the cURL request to check payment status
    $ch = curl_init($pesapal_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the request and get the response
    $response = curl_exec($ch);
    curl_close($ch);

    // Return the response data
    return json_decode($response, true);
}

// Check if both merchant reference and transaction tracking ID are present
if (isset($_GET['pesapal_merchant_reference']) && isset($_GET['pesapal_transaction_tracking_id'])) {
    $pesapal_merchant_reference = $_GET['pesapal_merchant_reference'];
    $pesapal_transaction_tracking_id = $_GET['pesapal_transaction_tracking_id'];

    // Get the payment status from Pesapal
    $response_data = checkPaymentStatus($pesapal_merchant_reference, $pesapal_transaction_tracking_id);

    if ($response_data['status'] === 'success') {
        // Payment was successful, update the payment status
        file_put_contents($statusFile, 'success');
        file_put_contents($amountFile, $response_data['amount']);

        // Redirect to the premium page
        header("Location: ?page=premium");
        exit;
    } else {
        // Payment failed, update the payment status as failed
        file_put_contents($statusFile, 'failed');
        file_put_contents($amountFile, '0');

        // Redirect to the payment failure page
        header("Location: payment_failed.php");
        exit;
    }
} else {
    // Invalid request, redirect to the payment failure page
    header("Location: payment_failed.php");
    exit;
}
?>
