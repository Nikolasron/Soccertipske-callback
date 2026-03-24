<?php
require_once 'config.php';

// Get callback parameters
$package = isset($_GET['package']) ? intval($_GET['package']) : 0;
$reference = isset($_GET['reference']) ? $_GET['reference'] : (isset($_POST['reference']) ? $_POST['reference'] : '');
$status = isset($_GET['status']) ? $_GET['status'] : (isset($_POST['status']) ? $_POST['status'] : '');

// Log the callback for debugging
$logEntry = date('Y-m-d H:i:s') . " - Package: $package, Reference: $reference, Status: $status\n";
file_put_contents('callback_log.txt', $logEntry, FILE_APPEND);

if ($status == 'success' || $status == 'completed') {
    // Update unlock tracker
    $unlockTrackerFile = 'unlock_tracker.json';
    $unlockTracker = file_exists($unlockTrackerFile) ? json_decode(file_get_contents($unlockTrackerFile), true) : [];
    
    $today = date('Y-m-d');
    if(!isset($unlockTracker['date']) || $unlockTracker['date'] != $today){
        $unlockTracker = ['date'=>$today,'unlocked'=>[]];
    }
    
    $unlockTracker['unlocked'][$package] = true;
    file_put_contents($unlockTrackerFile, json_encode($unlockTracker));
    
    // Redirect back to main page with success message
    header("Location: index.php?package=$package&payment_success=1#premium");
    exit;
} else {
    // Payment failed
    header("Location: index.php?package=$package&payment_failed=1#premium");
    exit;
}
?>
