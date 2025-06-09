
<?php
session_start();
include 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check authentication
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
// 
// Get user ID
$email = $_SESSION['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// Fetch and store ThingSpeak data with rate limiting
$last_fetch_file = 'last_thingspeak_fetch.txt';
$fetch_interval = 15; // seconds
if (!file_exists($last_fetch_file) || (time() - filemtime($last_fetch_file)) >= $fetch_interval) {
    $channel_id = "2927250";
    $read_api_key = "3HBH0XDXP6CJHOO2";
    $url = "https://api.thingspeak.com/channels/$channel_id/feeds.json?api_key=$read_api_key&results=1";
    
    $response = @file_get_contents($url);
    if ($response !== false) {
        $data = json_decode($response, true);
        if (isset($data['feeds'][0])) {
            $latest = $data['feeds'][0];
            $stmt = $conn->prepare("INSERT INTO sensor_reading (room_id, temperature, humidity, lux, head_count, recorded_at) 
                                   VALUES (1, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("dddi", 
                    $latest['field1'], 
                    $latest['field2'], 
                    $latest['field3'], 
                    $latest['field4']
                );
                $stmt->execute();
                $stmt->close();
                touch($last_fetch_file);
            }
        }
    }
}


// Fetch sensor reading data based on filters
// Process filters
$date_filter = $_GET['date'] ?? null;
$time_filter = $_GET['time'] ?? null;

// Set default date if time is provided without date
if ($time_filter && !$date_filter) {
    $date_filter = date('Y-m-d');
}

// Build base query
$sql = "SELECT rm.name AS room, sr.temperature, sr.lux, sr.humidity, sr.head_count, sr.recorded_at
        FROM sensor_reading sr
        INNER JOIN rooms rm ON sr.room_id = rm.id
        WHERE rm.user_id = ?";

$params = [];
$types = "i";
$params[] = &$user_id;

// Add date filter
if ($date_filter) {
    $sql .= " AND DATE(sr.recorded_at) = ?";
    $types .= "s";
    $params[] = &$date_filter;
}

// Add time filter
if ($time_filter) {
    list($hour, $minute) = explode(':', $time_filter);
    $sql .= " AND HOUR(sr.recorded_at) = ? AND MINUTE(sr.recorded_at) = ?";
    $types .= "ii";
    $params[] = &$hour;
    $params[] = &$minute;
}

// Finalize query
$sql .= " ORDER BY sr.recorded_at DESC";

// Prepare and execute statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
}

// Dynamic parameter binding
call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $params));
$stmt->execute();
$result = $stmt->get_result();

// Format response
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'room' => $row['room'],
        'temperature' => (float)$row['temperature'],
        'lux' => (int)$row['lux'],
        'humidity' => (float)$row['humidity'],
        'head_count' => (int)$row['head_count'],
        'recorded_at' => $row['recorded_at']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($data);
exit();
?>