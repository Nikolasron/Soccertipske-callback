<?php
// You can adjust these values with your actual keys.
$consumer_key = 'fscynXgvnF1MD8ZrosN/zegFf1EPBV5q'; // Your Pesapal Consumer Key
$consumer_secret = 'Xc6vsXcK8hoAg80eQkdaAP6sHVo='; // Your Pesapal Consumer Secret

// Pesapal API endpoint for checking payment status
$pesapal_endpoint = "https://www.pesapal.com/API/TransactionStatus";

// Get the parameters from the GET request (they are passed after the user completes the payment)
if (isset($_GET['pesapal_merchant_reference']) && isset($_GET['pesapal_transaction_tracking_id'])) {
    $pesapal_merchant_reference = $_GET['pesapal_merchant_reference'];
    $pesapal_transaction_tracking_id = $_GET['pesapal_transaction_tracking_id'];

    // Set up the request headers for Pesapal API authentication
    $headers = [
        'Authorization: Basic ' . base64_encode($consumer_key . ':' . $consumer_secret),
        'Content-Type: application/json'
    ];

    // Prepare the request body (transaction details)
    $request_data = [
        'merchant_reference' => $pesapal_merchant_reference,
        'transaction_tracking_id' => $pesapal_transaction_tracking_id
    ];

    // Set up the cURL request to check payment status
    $ch = curl_init($pesapal_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the request and get the response
    $response = curl_exec($ch);

    if ($response === false) {
        // Handle error before closing the cURL session
        echo "Error: " . curl_error($ch);
        curl_close($ch);
        exit;
    }

    // Close the cURL session
    curl_close($ch);

    // Parse the response from the API
    $response_data = json_decode($response, true);

    // Check the status of the payment
    if (isset($response_data['status']) && $response_data['status'] === 'success') {
        // Payment was successful, update the user's status
        file_put_contents(__DIR__.'/data/payment_status.txt', 'success');
        file_put_contents(__DIR__.'/data/paid_amount.txt', $response_data['amount']);

        // Optionally, log the successful transaction
        file_put_contents(__DIR__.'/data/payment_log.txt', "Transaction {$pesapal_merchant_reference}: Payment successful for amount {$response_data['amount']}.\n", FILE_APPEND);

        // You can redirect or output something to inform the user
        echo "Payment successful! Thank you for your payment.";
        // Optionally, redirect user to another page:
        // header("Location: success_page.php");
        // exit;

    } else {
        // Payment failed, handle accordingly
        file_put_contents(__DIR__.'/data/payment_status.txt', 'failed');
        file_put_contents(__DIR__.'/data/paid_amount.txt', '0');

        // Optionally, log the failed transaction
        file_put_contents(__DIR__.'/data/payment_log.txt', "Transaction {$pesapal_merchant_reference}: Payment failed.\n", FILE_APPEND);

        echo "Payment failed! Please try again.";
        // Optionally, redirect to a failure page:
        // header("Location: failure_page.php");
        // exit;
    }
} else {
    // If parameters are missing
    echo "Error: Missing payment details.";
}
?>
