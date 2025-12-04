<?php
require_once __DIR__ . '/config/config.php'; 

if(isset($_GET['ref'], $_GET['package'])){
    $ref = $_GET['ref'];
    $selectedPackage = intval($_GET['package']);
    
    $secretKey = PAYSTACK_SECRET_KEY;
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/$ref",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $secretKey"],
    ]);
    $response = curl_exec($curl);
    $res = json_decode($response, true);
    curl_close($curl);

    if($res['status'] && $res['data']['status'] == 'success'){
        // Unlock package logic (similar to main code)
        $unlockTrackerFile = 'unlock_tracker.json';
        $unlockTracker = file_exists($unlockTrackerFile) ? json_decode(file_get_contents($unlockTrackerFile), true) : [];
        $unlockTracker['unlocked'][$selectedPackage] = true;
        file_put_contents($unlockTrackerFile,json_encode($unlockTracker));
        echo "Payment verified successfully!";
    } else {
        echo "Payment verification failed!";
    }
} else {
    echo "No reference provided.";
}
