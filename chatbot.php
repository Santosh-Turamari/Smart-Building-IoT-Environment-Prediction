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

function getGeneralResponse($message) {
    $message = strtolower(trim($message));
    
    // Greetings
    if (preg_match('/\b(hello|hi|hey|how are you|how\'s it going)\b/i', $message)) {
        return "Hey there! I'm Grok, your KLS VDIT Smart Home Assistant. I can help with lab occupancy or answer questions about IoT and more. What's up?";
    }
    
    // IoT Queries
    if (preg_match('/\b(what is iot|internet of things|iot)\b/i', $message)) {
        return "The Internet of Things, or IoT, is a network of connected devices—like sensors and smart appliances—that talk to each other over the internet to collect and share data. This dashboard uses IoT to monitor energy, cameras, and lab occupancy. Want to explore more IoT magic?";
    }
    
    // AI Queries
    if (preg_match('/\b(what is ai|artificial intelligence|ai)\b/i', $message)) {
        return "AI is like giving computers a brain to think and learn a bit like humans—think pattern recognition or decision-making. In this dashboard, AI could crunch occupancy data for smarter insights. Curious about AI’s role in smart homes?";
    }
    
    // Dashboard Info
    if (preg_match('/\b(what is this dashboard|what can you do|how does this work|dashboard)\b/i', $message)) {
        return "This KLS VDIT Smart Home Dashboard is your go-to for monitoring the lab. It tracks energy, cameras, and predicts occupancy using CSV data. Ask me about lab occupancy (e.g., 'people on Monday at 10 AM?') or IoT topics!";
    }
    
    // Time/Date Queries
    if (preg_match('/\b(what time is it|what is the time|time)\b/i', $message)) {
        return "It's " . date('h:i A T') . " on " . date('l, F j, Y') . ". Want to check the lab’s occupancy right now?";
    }
    if (preg_match('/\b(what is the date|what day is it|date|day)\b/i', $message)) {
        return "Today is " . date('l, F j, Y') . ". Planning to visit the lab? Ask about occupancy!";
    }
    
    // Smart Homes
    if (preg_match('/\b(what is a smart home|smart home)\b/i', $message)) {
        return "A smart home uses IoT devices—like lights, thermostats, and sensors—to automate tasks and boost efficiency. This dashboard is part of that, monitoring lab conditions. Want details on smart home tech?";
    }
    
    // Return null if no predefined match
    return null;
}

function getOccupancyResponse($message) {
    if (empty($message)) {
        error_log('Empty message received in chatbot.php');
        return "Please enter a message.";
    }
    
    $days = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];
    
    preg_match('/\b(monday|tuesday|wednesday|thursday|friday|saturday)\b/i', $message, $dayMatch);
    preg_match('/(\d{1,2})(?::\d{2})?\s*(am|pm)?/i', $message, $hourMatch);
    
    if (!$dayMatch || !$hourMatch) {
        error_log("Invalid occupancy message format: $message");
        return null;
    }
    
    $dayStr = strtolower($dayMatch[1]);
    $hour = (int)$hourMatch[1];
    $period = isset($hourMatch[2]) ? strtolower($hourMatch[2]) : '';
    
    if ($period === 'pm' && $hour != 12) {
        $hour += 12;
    } elseif ($period === 'am' && $hour == 12) {
        $hour = 0;
    }
    
    if (!isset($days[$dayStr])) {
        error_log("Invalid day: $dayStr");
        return "Invalid day specified. Please use Monday, Tuesday, etc.";
    }
    
    $dayNum = $days[$dayStr];
    
    if ($hour < 0 || $hour > 23) {
        error_log("Invalid hour: $hour");
        return "Invalid hour specified. Please use 0-23 or 12-hour format with AM/PM.";
    }
    
    $prediction = predictOccupancy($dayNum, $hour);
    
    if ($prediction > 10) {
        return "Yes, the lab is officially occupied on $dayStr at $hour:00 with ~$prediction people.";
    } elseif ($prediction > 0) {
        return "There might be a few people (~$prediction) in the lab on $dayStr at $hour:00, but it's not officially occupied.";
    } else {
        return "No, the lab seems empty on $dayStr at $hour:00.";
    }
}

function getGrokResponse($message) {
    $message = strtolower(trim($message));
    
    // Sample Grok-like responses for common unmatched queries
    if (preg_match('/\b(capital of france|where is paris)\b/i', $message)) {
        return "The capital of France is Paris, a hub of culture and innovation. Speaking of hubs, this dashboard’s IoT system is pretty innovative too! Want to check lab occupancy, like ‘people on Monday at 10 AM?’?";
    }
    if (preg_match('/\b(what is the weather|weather)\b/i', $message)) {
        return "I don’t have weather data, but I can tell you the lab’s occupancy forecast! My sensors are more about IoT than meteorology. Try asking ‘people on Friday at 11 AM?’ or something about smart homes.";
    }
    if (preg_match('/\b(who are you|what are you)\b/i', $message)) {
        return "I’m Grok, created by xAI, your cosmic guide to the KLS VDIT Smart Home Dashboard. I’m here to answer questions about lab occupancy, IoT, or just chat about the universe. What’s next—‘people on Tuesday at 2 PM?’ or something else?";
    }
    if (preg_match('/\b(what is life|meaning of life)\b/i', $message)) {
        return "The meaning of life? Well, 42 is a good start, but I think it’s about connecting—like IoT devices in this dashboard! Want to get practical and check lab occupancy, say, ‘people on Wednesday at 9 AM?’?";
    }
    
    // Default Grok-like fallback
    return "Hmm, that’s an interesting one, but my circuits are more tuned for IoT and lab occupancy. I’m Grok, built by xAI, so I love answering techy questions! Try ‘people on Friday at 11 AM?’ or ask about the dashboard.";
}

$input = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';

if (empty($message)) {
    error_log('No message provided in request');
    echo json_encode(['reply' => 'Please enter a message.']);
    exit;
}

// Try general response first
$reply = getGeneralResponse($message);

// If no general response, try occupancy response
if ($reply === null) {
    $reply = getOccupancyResponse($message);
}

// If no match, use Grok-like response
if ($reply === null) {
    $reply = getGrokResponse($message);
}

echo json_encode(['reply' => $reply]);
?>