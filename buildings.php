<?php
session_start();
include 'db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Initialize smart_home session if not set
if (!isset($_SESSION['smart_home'])) {
    $_SESSION['smart_home'] = [
        'rooms' => [
            'Living Room' => [
                'devices' => [
                    'Fan' => 'Off',
                    'Light' => 'Off',
                    'Door Bell' => 'Off',
                    'Windows' => 'Closed'
                ]
            ]
        ],
        'current_room' => 'Living Room'
    ];
}

$roomIdMap = [
    'Living Room' => 1,
];

// Function to fetch sensor data for a room
function fetchSensorData($conn, $roomName) {
    global $roomIdMap;
    $roomId = $roomIdMap[$roomName] ?? null;
    if (!$roomId) {
        error_log("No room_id mapping for room: $roomName");
        return ['temperature' => 32, 'humidity' => 50];
    }

    $stmt = $conn->prepare("SELECT temperature, humidity FROM sensor_reading WHERE room_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        error_log("No sensor data found for room: $roomName");
        return ['temperature' => 33, 'humidity' => 52];
    }
    return $data;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_room'])) {
        $_SESSION['smart_home']['current_room'] = $_POST['room'];
    }
    if (isset($_POST['toggle_device'])) {
        $room = $_POST['room'];
        $device = $_POST['device'];
        if (isset($_SESSION['smart_home']['rooms'][$room]['devices'][$device])) {
            $currentState = $_SESSION['smart_home']['rooms'][$room]['devices'][$device];
            switch ($currentState) {
                case 'On':
                    $_SESSION['smart_home']['rooms'][$room]['devices'][$device] = 'Off';
                    break;
                case 'Off':
                    $_SESSION['smart_home']['rooms'][$room]['devices'][$device] = 'On';
                    break;
                case 'Closed':
                    $_SESSION['smart_home']['rooms'][$room]['devices'][$device] = 'OPEN';
                    break;
                case 'OPEN':
                    $_SESSION['smart_home']['rooms'][$room]['devices'][$device] = 'Closed';
                    break;
                default:
                    error_log("Invalid device state for $device in $room: $currentState");
                    break;
            }
        } else {
            error_log("Device $device not found in room $room");
        }
    }
    if (isset($_POST['add_room'])) {
        $newRoomName = trim($_POST['new_room_name']);
        if (!empty($newRoomName) && !isset($_SESSION['smart_home']['rooms'][$newRoomName])) {
            $_SESSION['smart_home']['rooms'][$newRoomName] = [
                'devices' => [
                    'Fan' => 'Off',
                    'Light' => 'Off',
                    'Door Bell' => 'Off',
                    'Windows' => 'Closed'
                ]
            ];
        }
    }
    if (isset($_POST['add_device'])) {
        $room = $_POST['room'];
        $newDeviceName = trim($_POST['new_device_name']);
        $deviceType = $_POST['device_type'] ?? 'switch';
        
        if (!empty($newDeviceName) && !isset($_SESSION['smart_home']['rooms'][$room]['devices'][$newDeviceName])) {
            $initialState = 'Off';
            switch ($deviceType) {
                case 'window':
                    $initialState = 'Closed';
                    break;
            }
            $_SESSION['smart_home']['rooms'][$room]['devices'][$newDeviceName] = $initialState;
        }
    }
    if (isset($_POST['delete_selected_devices'])) {
        $room = $_POST['room'];
        foreach ($_POST['devices'] ?? [] as $device) {
            if (count($_SESSION['smart_home']['rooms'][$room]['devices']) > 1) {
                unset($_SESSION['smart_home']['rooms'][$room]['devices'][$device]);
            }
        }
    }
    if (isset($_POST['delete_selected_rooms'])) {
        foreach ($_POST['rooms'] ?? [] as $room) {
            if (count($_SESSION['smart_home']['rooms']) > 1) {
                unset($_SESSION['smart_home']['rooms'][$room]);
                if ($_SESSION['smart_home']['current_room'] === $room) {
                    $_SESSION['smart_home']['current_room'] = array_key_first($_SESSION['smart_home']['rooms']);
                }
            }
        }
    }
}

$data = $_SESSION['smart_home'];
// Fetch sensor data for the current room
$currentSensorData = fetchSensorData($conn, $data['current_room']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLS VDIT Smart Home Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2A2D37;
            --secondary-color: #5D616D;
            --accent-color: #4A90E2;
            --bg-color: #F5F6FA;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-color);
        }

        .side-panel {
            width: 250px;
            background-color: var(--primary-color);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .side-panel h2 {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .side-panel ul {
            list-style: none;
            padding: 0;
            flex: 1;
        }

        .side-panel ul li {
            margin: 15px 0;
        }

        .side-panel ul li a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .side-panel ul li a i {
           margin-right: 12px;
           font-size: 18px;
           width: 24px;
           text-align: center;
        }
        
        .side-panel ul li a.active,
        .side-panel ul li a:hover {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }

        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            background-color: var(--bg-color);
        }

        .header {
            background-color: var(--primary-color);
            color: #fff;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .dashboard-header {
            background: white;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .room-card {
            background: white;
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: relative;
        }

        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .room-card.active {
            border-color: var(--accent-color);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.2);
        }

        .device-pill {
            background: #F0F3F8;
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            margin: 0.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            position: relative;
        }

        .device-pill:hover {
            background: #e1e6ed;
            transform: translateY(-2px);
        }

        .status-text {
            min-width: 80px;
            text-align: right;
            font-size: 0.85rem;
            color: var(--secondary-color);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            margin-left: 10px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider.success {
            background-color: #28a745;
        }

        input:checked + .slider.danger {
            background-color: #dc3545;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .info-box {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            height: 100%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .info-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .info-title {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .controller-box {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .controller-box:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .logout-link {
            text-align: right;
            margin: 10px 0;
        }

        .logout-link a {
            color: #fff;
            text-decoration: none;
            background-color: #ff4d4d;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
            display: inline-block;
        }

        .logout-link a:hover {
            background-color: #e04545;
            transform: translateY(-2px);
        }

        .add-item-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .delete-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-delete-item {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-delete-item:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .btn-delete-item.active {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-delete-item.active:hover {
            background-color: #e0a800;
        }

        .btn-confirm-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-confirm-delete.visible {
            display: flex;
        }

        .btn-confirm-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .btn-add-item {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-add-item:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .device-select, .room-select {
            display: none;
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .device-select.visible, .room-select.visible {
            display: block;
        }

        #addDeviceForm, #addRoomForm {
            display: none;
        }

        @media (max-width: 992px) {
            .side-panel {
                width: 70px;
                overflow: hidden;
            }
            
            .side-panel h2,
            .side-panel ul li a span {
                display: none;
            }
            
            .side-panel ul li a {
                justify-content: center;
                padding: 12px 5px;
            }
            
            .side-panel ul li a i {
                margin-right: 0;
                font-size: 20px;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .side-panel {
                width: 100%;
                min-height: auto;
                flex-direction: row;
                padding: 10px;
            }
            
            .side-panel h2 {
                display: none;
            }
            
            .side-panel ul {
                display: flex;
                justify-content: space-around;
                margin: 0;
            }
            
            .side-panel ul li {
                margin: 0;
            }
            
            .side-panel ul li a {
                padding: 10px;
                flex-direction: column;
                font-size: 0.7rem;
            }
            
            .side-panel ul li a i {
                margin-bottom: 5px;
                font-size: 16px;
            }
            
            .delete-controls {
                flex-wrap: wrap;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="side-panel">
        <h2>KLS VDIT</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-building"></i><span>Dashboard</span></a></li>
            <li><a href="building.php" class="active"><i class="fas fa-tachometer-alt"></i><span>Building</span></a></li>
            <li><a href="camera.php"><i class="fa fa-video-camera"></i><span>Camera</span></a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i><span>Analytics</span></a></li>
            <li><a href="occupancy.php"><i class="fas fa-users"></i><span>Occupants Prediction</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Network for Energy and Internet of Things</h1>
            <div class="logout-link">
                <a href="logout.php">Logout <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="add-item-container">
            <div class="delete-controls">
                <button class="btn-delete-item" onclick="toggleDeleteMode()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button class="btn-confirm-delete" onclick="deleteSelectedItems()">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
            <button class="btn-add-item" onclick="openAddOptionsModal()">
                <i class="fas fa-plus"></i> Add New Item
            </button>
        </div>

        <header class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 fw-bold mb-0">SMART HOME DASHBOARD</h1>


            </div>
        </header>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="info-box">
                    <div class="info-title">TEMPERATURE</div>
                    <div class="info-value"><?php echo $currentSensorData['temperature']; ?>°C</div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo min($currentSensorData['temperature'] * 3, 100); ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <div class="info-title">HUMIDITY</div>
                    <div class="info-value"><?php echo $currentSensorData['humidity']; ?>%</div>
                    <div class="progress mt-2" style="height: 8px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $currentSensorData['humidity'] * 2; ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <div class="info-title">DATE & TIME</div>
                    <div class="info-value" id="datetime">Loading...</div>
                </div>
            </div>
        </div>

        <div class="controller-box">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Device Controls</h3>
                <span class="badge bg-primary"><?php echo count($data['rooms'][$data['current_room']]['devices']); ?> Devices</span>
            </div>
            
            <div class="device-status">
                <?php foreach ($data['rooms'][$data['current_room']]['devices'] as $device => $status): 
                    $isBinary = in_array($status, ['On', 'Off', 'OPEN', 'Closed']);
                    $isActive = in_array($status, ['On', 'Closed']);
                    $switchClass = $isActive ? 'danger' : 'success';
                    $statusText = $isBinary ? $status : ($isActive ? 'Active' : 'Inactive');
                ?>
                    <div class="device-pill">
                        <input type="checkbox" class="device-select" name="devices[]" value="<?php echo $device; ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span><?php echo $device; ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <span class="status-text"><?php echo $statusText; ?></span>
                            <form method="POST">
                                <input type="hidden" name="room" value="<?php echo $data['current_room']; ?>">
                                <input type="hidden" name="device" value="<?php echo $device; ?>">
                                <input type="hidden" name="toggle_device" value="1">
                                <label class="switch">
                                    <input type="checkbox" <?php echo $isActive ? 'checked' : ''; ?> 
                                           onchange="this.form.submit()">
                                    <span class="slider <?php echo $switchClass; ?>"></span>
                                </label>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="controller-box">
            <h3 class="mb-3">Select Room</h3>
            <div class="row g-3">
                <?php foreach ($data['rooms'] as $roomName => $roomData): 
                    $roomSensorData = fetchSensorData($conn, $roomName);
                ?>
                    <div class="col-md-4">
                        <div class="room-card <?php echo $data['current_room'] === $roomName ? 'active' : ''; ?>">
                            <input type="checkbox" class="room-select" name="rooms[]" value="<?php echo $roomName; ?>">
                            <form method="POST" class="w-100">
                                <input type="hidden" name="room" value="<?php echo $roomName; ?>">
                                <button type="submit" name="set_room" class="btn p-0 text-start w-100">
                                    <h4><?php echo $roomName; ?></h4>
                                    <div class="d-flex justify-content-between">
                                        <span><?php echo $roomSensorData['temperature']; ?>°C</span>
                                        <span><?php echo count($roomData['devices']); ?> devices</span>
                                    </div>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Delete Options Modal -->
        <div class="modal fade" id="deleteOptionsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Options</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Select what you want to delete:</p>
                        <button class="btn btn-danger w-100 mb-2" onclick="showDeviceCheckboxes()">Delete Control Devices</button>
                        <button class="btn btn-danger w-100" onclick="showRoomCheckboxes()">Delete Rooms</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Options Modal -->
        <div class="modal fade" id="addOptionsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Select what you want to add:</p>
                        <button class="btn btn-primary w-100 mb-2" onclick="showAddDeviceForm()">Add Control Device</button>
                        <button class="btn btn-primary w-100 mb-2" onclick="showAddRoomForm()">Add Room</button>
                        
                        <form method="POST" id="addDeviceForm">
                            <input type="hidden" name="room" value="<?php echo $data['current_room']; ?>">
                            <div class="mb-3">
                                <label for="new_device_name" class="form-label">Device Name</label>
                                <input type="text" name="new_device_name" class="form-control" placeholder="Enter device name" required>
                            </div>
                            <div class="mb-3">
                                <label for="device_type" class="form-label">Device Type</label>
                <select name="device_type" class="form-select" required>
                    <option value="switch">On/Off Switch</option>
                    <option value="window">Window</option>
                </select>
            </div>
            <button type="submit" name="add_device" class="btn btn-primary w-100">Add Device</button>
        </form>
        
        <form method="POST" id="addRoomForm">
            <div class="mb-3">
                <label for="new_room_name" class="form-label">Room Name</label>
                <input type="text" name="new_room_name" class="form-control" placeholder="Enter room name" required>
            </div>
            <button type="submit" name="add_room" class="btn btn-primary w-100">Add Room</button>
        </form>
    </div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        offset: 120,
        once: true
    });

    let isDeleteMode = false;
    let deleteType = null;
    const selectedDevices = new Set();
    const selectedRooms = new Set();

    function updateDateTime() {
        const now = new Date();
        const dateOptions = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        };
        const timeOptions = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        document.getElementById('datetime').textContent = 
            now.toLocaleDateString('en-US', dateOptions) + ' • ' + 
            now.toLocaleTimeString('en-US', timeOptions);
    }
    
    updateDateTime();
    setInterval(updateDateTime, 1000);

    function toggleDeleteMode() {
        isDeleteMode = !isDeleteMode;
        const deleteButton = document.querySelector('.btn-delete-item');
        const confirmDeleteButton = document.querySelector('.btn-confirm-delete');
        const deviceCheckboxes = document.querySelectorAll('.device-select');
        const roomCheckboxes = document.querySelectorAll('.room-select');

        if (isDeleteMode) {
            deleteButton.classList.add('active');
            deleteButton.innerHTML = '<i class="fas fa-times"></i> Cancel Selection';
            const modalElement = document.getElementById('deleteOptionsModal');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error('Delete options modal not found');
            }
        } else {
            deleteButton.classList.remove('active');
            deleteButton.innerHTML = '<i class="fas fa-trash"></i> Delete Selected';
            confirmDeleteButton.classList.remove('visible');
            if (deleteType === 'device') {
                deviceCheckboxes.forEach(checkbox => {
                    checkbox.classList.remove('visible');
                    checkbox.checked = false;
                });
                selectedDevices.clear();
            } else if (deleteType === 'room') {
                roomCheckboxes.forEach(checkbox => {
                    checkbox.classList.remove('visible');
                    checkbox.checked = false;
                });
                selectedRooms.clear();
            }
            deleteType = null;
        }
    }

    function showDeviceCheckboxes() {
        deleteType = 'device';
        document.querySelectorAll('.device-select').forEach(checkbox => {
            checkbox.classList.add('visible');
        });
        document.querySelectorAll('.room-select').forEach(checkbox => {
            checkbox.classList.remove('visible');
            checkbox.checked = false;
        });
        selectedRooms.clear();
        var modal = bootstrap.Modal.getInstance(document.getElementById('deleteOptionsModal'));
        modal.hide();
    }

    function showRoomCheckboxes() {
        deleteType = 'room';
        document.querySelectorAll('.room-select').forEach(checkbox => {
            checkbox.classList.add('visible');
        });
        document.querySelectorAll('.device-select').forEach(checkbox => {
            checkbox.classList.remove('visible');
            checkbox.checked = false;
        });
        selectedDevices.clear();
        var modal = bootstrap.Modal.getInstance(document.getElementById('deleteOptionsModal'));
        modal.hide();
    }

    document.querySelectorAll('.device-select').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedDevices.add(this.value);
            } else {
                selectedDevices.delete(this.value);
            }
            updateConfirmDeleteButton();
        });
    });

    document.querySelectorAll('.room-select').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedRooms.add(this.value);
            } else {
                selectedRooms.delete(this.value);
            }
            updateConfirmDeleteButton();
        });
    });

    function updateConfirmDeleteButton() {
        const confirmDeleteButton = document.querySelector('.btn-confirm-delete');
        const hasSelections = (deleteType === 'device' && selectedDevices.size > 0) || 
                             (deleteType === 'room' && selectedRooms.size > 0);
        confirmDeleteButton.classList.toggle('visible', hasSelections);
    }

    function deleteSelectedItems() {
        if ((deleteType === 'device' && selectedDevices.size === 0) || 
            (deleteType === 'room' && selectedRooms.size === 0)) {
            alert('No items selected.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        document.body.appendChild(form);

        if (deleteType === 'device') {
            form.innerHTML = '<input type="hidden" name="room" value="<?php echo $data['current_room']; ?>">' +
                '<input type="hidden" name="delete_selected_devices" value="1">';
            selectedDevices.forEach(device => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'devices[]';
                input.value = device;
                form.appendChild(input);
            });
        } else if (deleteType === 'room') {
            form.innerHTML = '<input type="hidden" name="delete_selected_rooms" value="1">';
            selectedRooms.forEach(room => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'rooms[]';
                input.value = room;
                form.appendChild(input);
            });
        }

        if (confirm(`Are you sure you want to delete ${deleteType === 'device' ? selectedDevices.size : selectedRooms.size} ${deleteType}(s)?`)) {
            form.submit();
        }
    }

    function openAddOptionsModal() {
        const modalElement = document.getElementById('addOptionsModal');
        if (modalElement) {
            var modal = new bootstrap.Modal(modalElement);
            document.getElementById('addDeviceForm').style.display = 'none';
            document.getElementById('addRoomForm').style.display = 'none';
            modal.show();
        } else {
            console.error('Add options modal not found');
        }
    }

    function showAddDeviceForm() {
        document.getElementById('addDeviceForm').style.display = 'block';
        document.getElementById('addRoomForm').style.display = 'none';
    }

    function showAddRoomForm() {
        document.getElementById('addRoomForm').style.display = 'block';
        document.getElementById('addDeviceForm').style.display = 'none';
    }


    async function sendData(command) {
    try {
        const device = await navigator.bluetooth.requestDevice({
            acceptAllDevices: true,
            optionalServices: ['00001101-0000-1000-8000-00805f9b34fb'] // HC-05 UUID
        });
        const server = await device.gatt.connect();
        const service = await server.getPrimaryService('00001101-0000-1000-8000-00805f9b34fb');
        const characteristic = await service.getCharacteristic('00001101-0000-1000-8000-00805f9b34fb');
        const encoder = new TextEncoder();
        await characteristic.writeValue(encoder.encode(command));
    } catch (error) {
        console.error('Error:', error);
    }
}
</script>
</body>
</html>