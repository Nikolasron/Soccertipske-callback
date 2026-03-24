<?php
// Paynector Configuration
define('PAYNECTOR_PUBLIC_KEY', 'hmp_Q3jcdOhxLRNseayfrOg3X5rKu648yc3rwAYJjPJd');
define('PAYNECTOR_SECRET_KEY', 'hmp_Q3jcdOhxLRNseayfrOg3X5rKu648yc3rwAYJjPJd');

// For Paynector, the same key might be used for both public and secret
// But check your Paynector dashboard for specific public/secret keys

// Set to false when going live
define('PAYNECTOR_TEST_MODE', true);

// Callback URL for payment confirmation
define('PAYNECTOR_CALLBACK_URL', 'https://soccertipske-callback.onrender.com/callback.php');

// Base API URL (confirm with Paynector documentation)
define('PAYNECTOR_API_URL', 'https://api.paynector.com/v1');
?>
