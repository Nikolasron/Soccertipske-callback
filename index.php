<?php
// Load Paystack keys from Render environment variables
$paystack_public_key = getenv('PAYSTACK_PUBLIC_KEY');
$paystack_secret_key = getenv('PAYSTACK_SECRET_KEY');

if (!$paystack_public_key || !$paystack_secret_key) {
    die("Paystack keys are not set. Please check environment variables.");
}

// === SoccerTipsKE Main Page ===

// Function to read content safely from files
function readFileContent($filename) {
    return file_exists($filename) ? file_get_contents($filename) : "No content available.";
}

// Set timezone for midnight reset
date_default_timezone_set('Africa/Nairobi');

// Path to daily unlock tracking file
$unlockTrackerFile = 'unlock_tracker.json';

// Initialize unlock tracker
$unlockTracker = file_exists($unlockTrackerFile) ? json_decode(file_get_contents($unlockTrackerFile), true) : [];

// Reset unlocks at midnight
$today = date('Y-m-d');
if(!isset($unlockTracker['date']) || $unlockTracker['date'] != $today){
    $unlockTracker = ['date'=>$today,'unlocked'=>[]];
    file_put_contents($unlockTrackerFile,json_encode($unlockTracker));
}

// Handle payment verification (MPesa or Paystack)
$unlockMessage = "";
$premiumContent = "";

// Detect selected package
$selectedPackage = isset($_GET['package']) ? intval($_GET['package']) : 0;

// MPesa verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['mpesa_code'])) {

    $mpesa_code = trim($_POST['mpesa_code']);
    $statusFile = "payment_status.txt";

    if (file_exists($statusFile)) {
        $statusData = file_get_contents($statusFile);

        if (stripos($statusData, $mpesa_code) !== false) {
            $unlockTracker['unlocked'][$selectedPackage] = true;
            file_put_contents($unlockTrackerFile,json_encode($unlockTracker));
            $unlockMessage = "<p style='color:green;'>✅ Payment verified! Premium Predictions unlocked below.</p>";
        } else {
            $unlockMessage = "<p style='color:red;'>❌ Invalid or unverified MPesa code. Please try again.</p>";
        }
    } else {
        $unlockMessage = "<p style='color:red;'>No payment records found.</p>";
    }
}

// Paystack verification (callback)
if(isset($_GET['ref']) && $selectedPackage > 0){
    $ref = $_GET['ref'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/$ref",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer ".$paystack_secret_key],
    ]);
    $response = curl_exec($curl);
    $res = json_decode($response, true);
    curl_close($curl);

    if($res['status'] && $res['data']['status'] == 'success'){
        $unlockTracker['unlocked'][$selectedPackage] = true;
        file_put_contents($unlockTrackerFile,json_encode($unlockTracker));
        $unlockMessage = "<p style='color:green;'>✅ Payment verified via Paystack! Premium Predictions unlocked below.</p>";
    } else {
        $unlockMessage = "<p style='color:red;'>❌ Payment verification failed. Please try again.</p>";
    }
}

// Determine which premium content to show
if(isset($unlockTracker['unlocked'][$selectedPackage]) && $unlockTracker['unlocked'][$selectedPackage] === true){
    if($selectedPackage==1) $premiumContent = readFileContent("premium1.txt");
    if($selectedPackage==2) $premiumContent = readFileContent("premium2.txt");
    if($selectedPackage==3) $premiumContent = readFileContent("premium3.txt");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SoccerTipsKE - Free & Premium Predictions</title>
  <style>
    body { margin:0; font-family:Arial,sans-serif; background-color:#ffef80; color:#000; }
    header { background-color:#0047AB; color:white; text-align:center; padding:15px; }
    header h1 { margin:0; }
    nav { background-color:#003B8E; display:flex; justify-content:center; gap:20px; padding:10px; }
    nav a { color:white; text-decoration:none; font-weight:bold; }
    nav a:hover { text-decoration:underline; }
    section { padding:20px; }
    footer { background-color:#0047AB; color:white; text-align:center; padding:10px; font-size:14px; }
    .button { display:inline-block; padding:10px 20px; background-color:#0047AB; color:white; border-radius:6px; text-decoration:none; font-weight:bold; margin:3px; }
    .button:hover { background-color:#003B8E; }
    input[type="text"] { padding:8px; width:200px; border:1px solid #0047AB; border-radius:4px; }
    input[type="submit"] { padding:8px 15px; background-color:#0047AB; color:white; border:none; border-radius:4px; cursor:pointer; }
    input[type="submit"]:hover { background-color:#003B8E; }
    footer a { color:yellow; text-decoration:underline; }
  </style>
  <script src="https://js.paystack.co/v1/inline.js"></script>
</head>
<body>

<header>
  <h1>SoccerTipsKE</h1>
  <p>Your trusted source for football tips & predictions</p>
</header>

<nav>
  <a href="#free">Free Tips</a>
  <a href="#premium">Premium Predictions</a>
  <a href="#news">Football News</a>
</nav>

<section id="free">
  <h2>⚽ Free Tips</h2>
  <p><?php echo nl2br(readFileContent("tips.txt")); ?></p>
</section>

<section id="premium">
  <h2>💎 Premium Predictions</h2>

  <p><strong>🔥 You will receive predictions instantly after completing payment — no waiting!</strong></p>
  <hr>

  <p><strong>Premium Predictions 1:</strong> KSh 50 for 15 Odds</p>
  <p><strong>Premium Predictions 2:</strong> KSh 100 for 25 Odds</p>
  <p><strong>Premium Predictions 3:</strong> KSh 150 for 65 Odds</p>
  <hr>

  <label><strong>Select Premium Package:</strong></label><br>
  <select id="premium_select" style="padding:8px; border:1px solid #0047AB; border-radius:4px; margin:10px 0;">
      <option value="">-- Choose Package --</option>
      <option value="1" <?php if($selectedPackage==1) echo "selected"; ?>>KSh 50 - 15 Odds</option>
      <option value="2" <?php if($selectedPackage==2) echo "selected"; ?>>KSh 100 - 25 Odds</option>
      <option value="3" <?php if($selectedPackage==3) echo "selected"; ?>>KSh 150 - 65 Odds</option>
  </select>

  <script>
      document.getElementById("premium_select").addEventListener("change", function() {
          let p = this.value;
          if (p) window.location.href = "index.php?package=" + p + "#premium";
      });

      function payWithPaystack(amount, packageId){
          var handler = PaystackPop.setup({
              key: '<?php echo $paystack_public_key; ?>',
              email: 'customer@example.com',
              amount: amount*100,
              currency: 'KES',
              ref: ''+Math.floor((Math.random() * 1000000000) + 1),
              callback: function(response){
                  window.location.href = "https://soccertipske-callback.onrender.com/?package=" + packageId + "&ref=" + response.reference + "#premium";
              },
              onClose: function(){ alert('Payment window closed.'); },
              metadata: { package: packageId }
          });
          handler.openIframe();
      }
  </script>

  <br><br>

  <?php echo $unlockMessage; ?>

  <?php if ($premiumContent): ?>
    <div><?php echo nl2br($premiumContent); ?></div>
  <?php else: ?>
    <p>To unlock Premium Predictions, please pay via Paystack below or enter MPesa code.</p>

    <button class="button" onclick="payWithPaystack(50,1)">Pay KSh 50 (15 Odds)</button>
    <button class="button" onclick="payWithPaystack(100,2)">Pay KSh 100 (25 Odds)</button>
    <button class="button" onclick="payWithPaystack(150,3)">Pay KSh 150 (65 Odds)</button>

    <br><br>

    <p><strong>Returning user?</strong> Enter the MPesa code or Tracking ID you used during payment to unlock your premium predictions again.</p>

    <form method="POST">
      <label>Enter M-Pesa Code / Tracking ID:</label><br>
      <input type="text" name="mpesa_code" placeholder="e.g. STKE_1731234567" required>
      <input type="submit" value="Unlock">
    </form>
  <?php endif; ?>
</section>

<section id="news">
  <h2>📰 Football News</h2>
  <p><?php echo nl2br(readFileContent("news.txt")); ?></p>
</section>

<footer>
  &copy; <?php echo date("Y"); ?> SoccerTipsKE |
  <a href="terms.php">Terms & Conditions</a> |
  <a href="https://www.begambleaware.org" target="_blank">Gamble Aware</a>
</footer>

</body>
</html>
