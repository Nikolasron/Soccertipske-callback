<?php
// Used by frontend to confirm unlock
header("Content-Type: application/json");

$file = "unlock_tracker.json";

if (!file_exists($file)) {
    echo json_encode(["unlocked"=>false]);
    exit;
}

$data = json_decode(file_get_contents($file), true);

echo json_encode($data);
