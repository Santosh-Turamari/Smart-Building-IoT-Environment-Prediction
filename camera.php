<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';

// Validate session
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    $_SESSION['error'] = "Please log in to access the dashboard.";
    header("Location: index.php");
    exit();
}

// Get user_id from users table based on email
$email = $_SESSION['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again later.";
    header("Location: index.php");
    exit();
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("User not found for email: $email");
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

// Fetch cameras for the logged-in user
$stmt = $conn->prepare("SELECT id, name, location, status, type FROM cameras WHERE user_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Database error. Please try again later.";
    header("Location: camera.php");
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cameras = [];
while ($row = $result->fetch_assoc()) {
    $cameras[] = $row;
}

// Handle camera update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_camera'])) {
    $camera_id = filter_input(INPUT_POST, 'camera_id', FILTER_VALIDATE_INT);
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $location = trim(filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING));
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if (!$camera_id || !$name || !$location || !in_array($status, ['Online', 'Offline'])) {
        $_SESSION['error'] = "Invalid input data.";
        header("Location: camera.php");
        exit();
    }

    $stmt = $conn->prepare("UPDATE cameras SET name = ?, location = ?, status = ? WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: camera.php");
        exit();
    }
    $stmt->bind_param("sssii", $name, $location, $status, $camera_id, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Camera updated successfully.";
    } else {
        error_log("Update failed: " . $stmt->error);
        $_SESSION['error'] = "Failed to update camera: " . $stmt->error;
    }
    header("Location: camera.php");
    exit();
}

// Handle camera addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_camera'])) {
    $name = trim(filter_input(INPUT_POST, 'cameraName', FILTER_SANITIZE_STRING));
    $location = trim(filter_input(INPUT_POST, 'cameraLocation', FILTER_SANITIZE_STRING));
    $type = filter_input(INPUT_POST, 'cameraType', FILTER_SANITIZE_STRING);
    $status = 'Online'; // Default status for new cameras

    if (!$name || !$location || !in_array($type, ['built-in', 'usb'])) {
        $_SESSION['error'] = "Invalid input data.";
        header("Location: camera.php");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO cameras (user_id, name, location, type, status) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: camera.php");
        exit();
    }
    $stmt->bind_param("issss", $user_id, $name, $location, $type, $status);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Camera added successfully.";
    } else {
        error_log("Insert failed: " . $stmt->error);
        $_SESSION['error'] = "Failed to add camera: " . $stmt->error;
    }
    header("Location: camera.php");
    exit();
}

// Handle camera deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cameras'])) {
    $camera_ids = json_decode($_POST['camera_ids'], true);
    if (!is_array($camera_ids) || empty($camera_ids)) {
        error_log("Invalid or empty camera_ids: " . $_POST['camera_ids']);
        $_SESSION['error'] = "No cameras selected for deletion.";
        header("Location: camera.php");
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($camera_ids), '?'));
    $stmt = $conn->prepare("DELETE FROM cameras WHERE id IN ($placeholders) AND user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database error. Please try again later.";
        header("Location: camera.php");
        exit();
    }
    $types = str_repeat('i', count($camera_ids)) . 'i';
    $stmt->bind_param($types, ...array_merge($camera_ids, [$user_id]));
    if ($stmt->execute()) {
        $_SESSION['success'] = count($camera_ids) . " camera(s) deleted successfully.";
    } else {
        error_log("Delete failed: " . $stmt->error);
        $_SESSION['error'] = "Failed to delete cameras: " . $stmt->error;
    }
    header("Location: camera.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLS VDIT Camera Dashboard</title>
    
    <!-- Frameworks -->
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

        .camera-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .camera-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .camera-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .camera-preview {
            height: 180px;
            background-color: #2A2D37;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }
        
        .camera-preview img, .camera-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .camera-status {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-online {
            background-color: #28a745;
            color: white;
        }
        
        .status-offline {
            background-color: #dc3545;
            color: white;
        }
        
        .camera-info {
            padding: 15px;
        }
        
        .camera-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .camera-location {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 15px;
        }
        
        .camera-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }
        
        .btn-view, .btn-edit {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-view:hover, .btn-edit:hover {
            background-color: #3a7bc8;
            transform: translateY(-2px);
        }
        
        .btn-add-camera {
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
        
        .btn-add-camera:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        .btn-delete-camera {
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

        .btn-delete-camera:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .btn-delete-camera.active {
            background-color: #ffc107;
            color: #212529;
        }

        .btn-delete-camera.active:hover {
            background-color: #e0a800;
            transform: translateY(-2px);
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
            margin-left: 10px;
        }

        .btn-confirm-delete.visible {
            display: flex;
        }

        .btn-confirm-delete:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        .camera-select {
            display: none;
        }

        .camera-select.visible {
            display: block;
        }
        
        .add-camera-container {
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

        .video-player {
            width: 100%;
            height: auto;
            max-height: 80vh;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.9rem;
            margin-top: 5px;
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
            
            .main-content {
                padding-left: 10px;
                padding-right: 10px;
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
                margin-right: 0;
                margin-bottom: 5px;
                font-size: 16px;
            }
            
            .main-content {
                padding-bottom: 20px;
            }
            
            .camera-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .delete-controls {
                flex-wrap: wrap;
                gap: 10px;
            }

            .btn-confirm-delete {
                margin-left: 0;
            }

            .camera-actions {
                flex-direction: column;
                align-items: flex-end;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Side Panel -->
    <div class="side-panel">
        <h2>KLS VDIT</h2>
        <ul>
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-building"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="buildings.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Building</span>
                </a>
            </li>
            <li>
                <a href="camera.php" class="active">
                    <i class="fa fa-video-camera" aria-hidden="true"></i>
                    <span>Camera</span>
                </a>
            </li>
            <li>
                <a href="analytics.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li><a href="occupancy.php"><i class="fas fa-users"></i><span>Occupants Prediction</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Network for Energy and Internet of Things</h1>
            <div class="logout-link">
                <a href="logout.php">Logout <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <!-- Camera Management Section -->
        <div class="add-camera-container">
            <div class="delete-controls">
                <button class="btn-delete-camera" onclick="toggleDeleteMode()">
                    <i class="fas fa-trash"></i> Delete Camera
                </button>
                <button class="btn-confirm-delete" onclick="deleteSelectedCameras()">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
            </div>
            <button class="btn-add-camera" onclick="openAddCameraModal()">
                <i class="fas fa-plus"></i> Add New Camera
            </button>
        </div>

        <h2>Camera Dashboard</h2>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Camera Grid -->
        <div class="camera-grid">
            <?php foreach ($cameras as $camera): ?>
                <div class="camera-card">
                    <div class="camera-preview">
                        <?php if ($camera['status'] === 'Online'): ?>
                            <video id="webcam-preview-<?php echo $camera['id']; ?>" autoplay playsinline muted></video>
                        <?php else: ?>
                            <img src="https://via.placeholder.com/300x180/2A2D37/FFFFFF?text=Camera+Offline" alt="Camera Offline">
                        <?php endif; ?>
                        <span class="camera-status status-<?php echo strtolower($camera['status']); ?>"><?php echo htmlspecialchars($camera['status']); ?></span>
                        <input type="checkbox" class="camera-select" data-camera-id="<?php echo $camera['id']; ?>" style="position: absolute; top: 10px; left: 10px;">
                    </div>
                    <div class="camera-info">
                        <div class="camera-name"><?php echo htmlspecialchars($camera['name']); ?></div>
                        <div class="camera-location"><?php echo htmlspecialchars($camera['location']); ?></div>
                        <div class="camera-actions">
                            <button class="btn-view" onclick="viewCamera(<?php echo $camera['id']; ?>, '<?php echo addslashes($camera['name']); ?>', '<?php echo $camera['type']; ?>', '<?php echo $camera['status']; ?>')" data-bs-toggle="modal" data-bs-target="#viewCameraModal<?php echo $camera['id']; ?>">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editCameraModal<?php echo $camera['id']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit Camera Modal -->
                <div class="modal fade" id="editCameraModal<?php echo $camera['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Camera #<?php echo $camera['id']; ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="camera_id" value="<?php echo $camera['id']; ?>">
                                    <div class="mb-3">
                                        <label for="name-<?php echo $camera['id']; ?>" class="form-label">Name</label>
                                        <input type="text" name="name" id="name-<?php echo $camera['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($camera['name']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="location-<?php echo $camera['id']; ?>" class="form-label">Location</label>
                                        <input type="text" name="location" id="location-<?php echo $camera['id']; ?>" class="form-control" value="<?php echo htmlspecialchars($camera['location']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status-<?php echo $camera['id']; ?>" class="form-label">Status</label>
                                        <select name="status" id="status-<?php echo $camera['id']; ?>" class="form-select" required onchange="updatePreview(this, <?php echo $camera['id']; ?>)">
                                            <option value="Online" <?php echo $camera['status'] === 'Online' ? 'selected' : ''; ?>>Online</option>
                                            <option value="Offline" <?php echo $camera['status'] === 'Offline' ? 'selected' : ''; ?>>Offline</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" name="update_camera" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- View Camera Modal -->
                <div class="modal fade" id="viewCameraModal<?php echo $camera['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Live Feed: <?php echo htmlspecialchars($camera['name']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <?php if ($camera['status'] === 'Online'): ?>
                                    <video id="webcam-live-<?php echo $camera['id']; ?>" class="video-player" autoplay playsinline></video>
                                    <div id="error-<?php echo $camera['id']; ?>" class="error-message"></div>
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/640x480/2A2D37/FFFFFF?text=Camera+Offline" alt="Camera Offline" class="video-player">
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($cameras)): ?>
                <div class="text-center">No cameras found for this user.</div>
            <?php endif; ?>
        </div>

        <!-- Add Camera Modal -->
        <div class="modal fade" id="addCameraModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Camera</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" id="addCameraForm">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="cameraName" class="form-label">Camera Name</label>
                                <input type="text" class="form-control" id="cameraName" name="cameraName" required>
                            </div>
                            <div class="mb-3">
                                <label for="cameraLocation" class="form-label">Location</label>
                                <input type="text" class="form-control" id="cameraLocation" name="cameraLocation" required>
                            </div>
                            <div class="mb-3">
                                <label for="cameraType" class="form-label">Camera Type</label>
                                <select class="form-select" id="cameraType" name="cameraType" required>
                                    <option value="">Select camera type</option>
                                    <option value="built-in">Built-in Camera</option>
                                    <option value="usb">USB Webcam</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_camera" class="btn btn-primary">Add Camera</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            offset: 120,
            once: true
        });

        // Track selected cameras and delete mode
        const selectedCameras = new Set();
        let isDeleteMode = false;

        function toggleDeleteMode() {
            isDeleteMode = !isDeleteMode;
            const deleteButton = document.querySelector('.btn-delete-camera');
            const confirmDeleteButton = document.querySelector('.btn-confirm-delete');
            const checkboxes = document.querySelectorAll('.camera-select');

            if (isDeleteMode) {
                deleteButton.classList.add('active');
                deleteButton.innerHTML = '<i class="fas fa-times"></i> Cancel Selection';
                checkboxes.forEach(checkbox => {
                    checkbox.classList.add('visible');
                });
            } else {
                deleteButton.classList.remove('active');
                deleteButton.innerHTML = '<i class="fas fa-trash"></i> Delete Camera';
                confirmDeleteButton.classList.remove('visible');
                checkboxes.forEach(checkbox => {
                    checkbox.classList.remove('visible');
                    checkbox.checked = false;
                });
                selectedCameras.clear();
            }
        }

        // Handle checkbox changes
        document.querySelectorAll('.camera-select').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const cameraId = this.getAttribute('data-camera-id');
                const confirmDeleteButton = document.querySelector('.btn-confirm-delete');
                if (this.checked) {
                    selectedCameras.add(cameraId);
                } else {
                    selectedCameras.delete(cameraId);
                }
                // Show confirm delete button only when cameras are selected and in delete mode
                confirmDeleteButton.classList.toggle('visible', selectedCameras.size > 0 && isDeleteMode);
                console.log('Selected cameras:', Array.from(selectedCameras)); // Debug
            });
        });

        function deleteSelectedCameras() {
            if (selectedCameras.size === 0) {
                alert('No cameras selected for deletion.');
                return;
            }

            if (confirm(`Are you sure you want to delete ${selectedCameras.size} camera(s)?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_cameras';
                input.value = '1';
                form.appendChild(input);

                const cameraIdsInput = document.createElement('input');
                cameraIdsInput.type = 'hidden';
                cameraIdsInput.name = 'camera_ids';
                cameraIdsInput.value = JSON.stringify(Array.from(selectedCameras).map(id => parseInt(id)));
                form.appendChild(cameraIdsInput);

                document.body.appendChild(form);
                console.log('Submitting delete form with camera_ids:', cameraIdsInput.value); // Debug
                form.submit();
            }
        }

        function openAddCameraModal() {
            const modal = new bootstrap.Modal(document.getElementById('addCameraModal'));
            modal.show();
        }

        // Function to stream a camera by type
        async function streamCamera(cameraId, videoElementId, cameraType) {
            const videoElement = document.getElementById(videoElementId);
            const errorElement = document.getElementById(`error-${cameraId}`) || videoElement.parentElement;
            try {
                // Check if navigator.mediaDevices is available
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Camera access is not supported in this browser.');
                }

                // Enumerate all video devices
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                
                if (videoDevices.length === 0) {
                    throw new Error('No video devices found. Please connect a camera.');
                }

                console.log('Available video devices:', videoDevices); // Debug

                let deviceId;
                if (cameraType === 'built-in') {
                    // Select built-in camera
                    const builtInDevice = videoDevices.find(device => 
                        device.label.toLowerCase().includes('integrated') || 
                        device.label.toLowerCase().includes('built-in') ||
                        device.label.toLowerCase().includes('default')
                    ) || videoDevices[0]; // Fallback to first device if no built-in found
                    deviceId = builtInDevice.deviceId;
                    console.log('Selected built-in camera:', builtInDevice);
                } else if (cameraType === 'usb') {
                    // Select any non-built-in camera (external webcam)
                    const builtInDevice = videoDevices.find(device => 
                        device.label.toLowerCase().includes('integrated') || 
                        device.label.toLowerCase().includes('built-in') ||
                        device.label.toLowerCase().includes('default')
                    );
                    const externalDevices = builtInDevice 
                        ? videoDevices.filter(device => device.deviceId !== builtInDevice.deviceId)
                        : videoDevices; // If no built-in, all devices are considered external
                    if (externalDevices.length === 0) {
                        throw new Error('No external webcams found. Please connect an external camera.');
                    }
                    // Select the first available external device
                    deviceId = externalDevices[0].deviceId;
                    console.log('Selected external webcam:', externalDevices[0]);
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { deviceId: { exact: deviceId } }
                });
                videoElement.srcObject = stream;
                videoElement.play();
                errorElement.textContent = ''; // Clear any previous errors
            } catch (error) {
                console.error('Error streaming camera:', error);
                errorElement.textContent = `Unable to access camera: ${error.message}. Please check permissions and device availability.`;
            }
        }

        function viewCamera(cameraId, cameraName, cameraType, status) {
            if (status === 'Online') {
                streamCamera(cameraId, `webcam-live-${cameraId}`, cameraType);
            }
        }

        // Function to update preview when status changes in edit modal
        function updatePreview(select, cameraId) {
            const previewElement = document.getElementById(`webcam-preview-${cameraId}`);
            const status = select.value;
            if (status === 'Offline' && previewElement.tagName === 'VIDEO') {
                if (previewElement.srcObject) {
                    previewElement.srcObject.getTracks().forEach(track => track.stop());
                    previewElement.srcObject = null;
                }
                const img = document.createElement('img');
                img.src = 'https://via.placeholder.com/300x180/2A2D37/FFFFFF?text=Camera+Offline';
                img.alt = 'Camera Offline';
                previewElement.parentNode.replaceChild(img, previewElement);
            }
        }

        // Initialize previews for all cameras
        document.addEventListener('DOMContentLoaded', () => {
            <?php foreach ($cameras as $camera): ?>
                <?php if ($camera['status'] === 'Online'): ?>
                    streamCamera(<?php echo $camera['id']; ?>, 'webcam-preview-<?php echo $camera['id']; ?>', '<?php echo $camera['type']; ?>');
                <?php endif; ?>
            <?php endforeach; ?>

            // Stop streams when modals close
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('hidden.bs.modal', () => {
                    const video = modal.querySelector('video');
                    if (video && video.srcObject) {
                        video.srcObject.getTracks().forEach(track => track.stop());
                        video.srcObject = null;
                    }
                });
            });
        });
    </script>
</body>
</html>