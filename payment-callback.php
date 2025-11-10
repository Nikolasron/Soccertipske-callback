<?php
// This script handles the payment callback from Pesapal

// Get the payment status and any other parameters sent by Pesapal
$status = $_GET['status'] ?? ''; // Payment status (success or failure)
$prediction1 = $_GET['prediction1'] ?? '';
$prediction2 = $_GET['prediction2'] ?? '';
$prediction3 = $_GET['prediction3'] ?? '';
$countryCode = $_GET['country_code'] ?? '';
$phoneNumber = $_GET['phone_number'] ?? '';

// Handle the response based on payment status
if ($status === 'success') {
    // Payment was successful, show a success message and the user's predictions
    echo "<h1>Payment Successful!</h1>";
    echo "<p>Your predictions are: $prediction1, $prediction2, $prediction3</p>";
    echo "<p>We will contact you soon.</p>";
    echo "<a href='https://soccertpske.wuaze.com'>Go back to the predictions page</a>";
} else {
    // Payment failed, show a failure message
    echo "<h1>Payment Failed!</h1>";
    echo "<p>Please try again later or contact support.</p>";
    echo "<a href='https://soccertpske.wuaze.com'>Go back to the predictions page</a>";
}
?>
