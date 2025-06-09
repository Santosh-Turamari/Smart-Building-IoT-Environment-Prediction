<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function readOccupancyData($csvFile) {
    $data = [];
    if (!file_exists($csvFile)) {
        error_log("CSV file not found: $csvFile");
        return $data;
    }
    
    $file = fopen($csvFile, 'r');
    if ($file === false) {
        error_log("Failed to open CSV file: $csvFile");
        return $data;
    }
    
    // Skip header
    fgetcsv($file);
    
    while (($row = fgetcsv($file)) !== false) {
        if (count($row) >= 3) {
            $day = (int)$row[0];
            $hour = (int)$row[1];
            $occupants = (float)$row[2];
            $data[$day][$hour] = $occupants;
        }
    }
    
    fclose($file);
    return $data;
}

function predictOccupancy($day, $hour) {
    $csvFile = 'occupancy_data.csv';
    $avgOccupants = readOccupancyData($csvFile);
    return isset($avgOccupants[$day][$hour]) ? round($avgOccupants[$day][$hour]) : 0;
}

$input = json_decode(file_get_contents('php://input'), true);
$day = isset($input['day']) ? (int)$input['day'] : 1;
$hour = isset($input['hour']) ? (int)$input['hour'] : 9;

if ($day < 1 || $day > 6 || $hour < 0 || $hour > 23) {
    echo json_encode(['prediction' => 0]);
    exit;
}

$prediction = predictOccupancy($day, $hour);
echo json_encode(['prediction' => $prediction]);
?>