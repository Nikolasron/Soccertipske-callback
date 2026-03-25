<?php
// ============================================
// Paynectar Configuration - SOCCERTIPSKE
// ============================================

// 🔴 IMPORTANT: REPLACE WITH YOUR NEW API KEY
// Go to Paynector dashboard -> Regenerate API Key
// Then paste the NEW key between the quotes below
define('PAYNECTAR_SECRET_KEY', 'hmp_YJhMK3H8lIy7AsgsS4K56pInqCuVqny49IMWSbKr');

// Paynector API Endpoint
define('PAYNECTOR_API_URL', 'https://api.paynector.co.ke/api/v1');

// Callback URL on Render.com
define('PAYNECTOR_CALLBACK_URL', 'https://soccertipske-callback.onrender.com/callback.php');

// Your main site URL
define('SITE_URL', 'https://your-infinityfree.com'); // Change this!

// Set to false when going live
define('PAYNECTOR_TEST_MODE', true);

// Business details for M-Pesa
define('BUSINESS_PAYBILL', '174379'); // Replace with your Paybill
define('BUSINESS_TILL', ''); // Or use Till number
?>
