<?php
// ============================================
// Paynectar Configuration - SOCCERTIPSKE
// ============================================

// 🔴 IMPORTANT: REPLACE WITH YOUR PAYNECTAR API KEY
// Go to Paynectar dashboard -> Get your API key
define('PAYNECTAR_SECRET_KEY', 'YOUR_PAYNECTAR_API_KEY_HERE');

// Paynectar API Endpoint
define('PAYNECTAR_API_URL', 'https://api.paynectar.co.ke/api/v1');

// Callback URL on Render.com
define('PAYNECTAR_CALLBACK_URL', 'https://soccertipske-callback.onrender.com/callback.php');

// Your main site URL (InfinityFree)
define('SITE_URL', 'https://your-infinityfree-site.com'); // Change this!

// Set to false when going live
define('PAYNECTAR_TEST_MODE', true);

// Business details for M-Pesa
define('BUSINESS_PAYBILL', '174379'); // Replace with your Paybill
define('BUSINESS_TILL', ''); // Or use Till number
?>
