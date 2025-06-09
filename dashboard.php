<?php
session_start();
include 'db.php';

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Get user_id from users table based on email
$email = $_SESSION['user'];
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: index.php");
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$stmt->close();

// Handle date and time filters
$date_filter = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : null;
$time_filter = isset($_GET['time']) && !empty($_GET['time']) ? $_GET['time'] : null;

// If time is selected without date, use current date
if ($time_filter && !$date_filter) {
    $date_filter = date('Y-m-d');
}

// Prepare SQL query for sensor_reading data with LIMIT
$sql = "SELECT rm.name AS room, sr.temperature, sr.lux, sr.humidity, sr.head_count, sr.recorded_at 
        FROM sensor_reading sr 
        INNER JOIN rooms rm ON sr.room_id = rm.id 
        WHERE rm.user_id = ?";

$params = [];
$types = "i";
$params[] = &$user_id;

if ($date_filter) {
    $sql .= " AND DATE(sr.recorded_at) = ?";
    $types .= "s";
    $params[] = &$date_filter;
}

if ($time_filter) {
    list($hour, $minute) = explode(':', $time_filter);
    $sql .= " AND HOUR(sr.recorded_at) = ? AND MINUTE(sr.recorded_at) = ?";
    $types .= "ii";
    $params[] = &$hour;
    $params[] = &$minute;
}

$sql .= " ORDER BY sr.recorded_at DESC LIMIT 10";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die("An error occurred. Please try again later.");
}

// Dynamic parameter binding
call_user_func_array(array($stmt, 'bind_param'), array_merge(array($types), $params));
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLS VDIT Buildings Dashboard</title>

    <!-- Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Leaflet CSS for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

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
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            color: rgba(255, 255, 255, 0.8);
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
            background-color: rgba(255, 255, 255, 0.1);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .building-position {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .map-container {
            width: 100%;
            overflow: hidden;
            border-radius: 10px;
            margin-top: 10px;
        }

        .map-container img {
            width: 100%;
            height: 300px;
            display: block;
        }

        #openstreetmap {
            width: 100%;
            height: 300px;
            display: none;
        }

        .map-toggle-btn {
            margin-top: 10px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .map-toggle-btn:hover {
            background-color: #3a7bc8;
        }

        .rooms-table {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            flex: 1;
        }

        .table-container {
            max-height: 400px;
            overflow-y: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--secondary-color);
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .table tr:hover {
            background-color: #f8f9fa;
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

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-normal {
            background-color: #28a745;
        }

        .status-warning {
            background-color: #ffc107;
        }

        .status-danger {
            background-color: #dc3545;
        }

        .filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .date-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-filter input[type="date"],
        .date-filter input[type="time"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .date-filter button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .date-filter button:hover {
            background-color: #3a7bc8;
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

            .table-container {
                overflow-x: auto;
            }

            #openstreetmap {
                height: 250px;
            }

            .filter-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .date-filter {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .building-position h2,
            .rooms-table h2 {
                font-size: 1.2rem;
            }

            .table th,
            .table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }

            #openstreetmap {
                height: 200px;
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
                <a href="dashboard.php" class="active">
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
                <a href="camera.php">
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

        <!-- Building Position -->
        <div class="building-position">
            <h2>Building Position</h2>
            <div class="map-container">
                <img src="images/map.png" alt="Location" class="img-fluid" id="static-map">
                <div id="openstreetmap"></div>
            </div>
            <button class="map-toggle-btn" id="toggle-map-btn">Switch to OpenStreetMap</button>
        </div>

        <!-- Sensor Data Table -->
        <div class="filter-container">
            <h2>Room Condition</h2>
            <div class="date-filter">
                <form id="filter-form">
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter ?? ''); ?>">
                    <input type="time" name="time" value="<?php echo htmlspecialchars($time_filter ?? ''); ?>" step="60">
                    <button type="submit" id="filter-btn">Filter</button>
                </form>
            </div>
        </div>

        <div class="rooms-table">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Temperature</th>
                            <th>Luminosity</th>
                            <th>Humidity</th>
                            <th>Head Count</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody id="sensor-data">
                        <?php while ($row = $result->fetch_assoc()):
                            $tempStatus = ($row['temperature'] > 30) ? 'status-danger' : (($row['temperature'] > 25) ? 'status-warning' : 'status-normal');
                            $humidityStatus = ($row['humidity'] > 70) ? 'status-danger' : (($row['humidity'] > 50) ? 'status-warning' : 'status-normal');
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['room']); ?></td>
                                <td><span class="<?php echo $tempStatus; ?>"></span><?php echo htmlspecialchars($row['temperature']); ?> °C</td>
                                <td><?php echo htmlspecialchars($row['lux']); ?> Lux</td>
                                <td><span class="<?php echo $humidityStatus; ?>"></span><?php echo htmlspecialchars($row['humidity']); ?>%</td>
                                <td><?php echo htmlspecialchars($row['head_count']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['recorded_at']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($result->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center">No data found for this date/time. Try a different date or time.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Leaflet JS for OpenStreetMap -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        AOS.init({
            duration: 800,
            offset: 120,
            once: true
        });

        let dimensione = document.getElementById('openstreetmap');
        let mapInitialized = false;
        const staticMap = document.getElementById('static-map');
        const osmMap = document.getElementById('openstreetmap');
        const toggleBtn = document.getElementById('toggle-map-btn');

        function initMap() {
            const map = L.map('openstreetmap').setView([15.322561, 74.754356], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            L.marker([15.322561, 74.754356]).addTo(map)
                .bindPopup('Device Location')
                .openPopup();
            mapInitialized = true;
        }

        toggleBtn.addEventListener('click', function() {
            if (osmMap.style.display === 'none') {
                staticMap.style.display = 'none';
                osmMap.style.display = 'block';
                toggleBtn.textContent = 'Switch to Static Map';
                if (!mapInitialized) {
                    initMap();
                }
            } else {
                staticMap.style.display = 'block';
                osmMap.style.display = 'none';
                toggleBtn.textContent = 'Switch to OpenStreetMap';
            }
        });

        let fetchInterval = null;

        // Custom function to format date in 24-hour format
        function formatDateTo24Hour(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

        function fetchData(dateFilter, timeFilter) {
            const queryParams = new URLSearchParams();
            if (dateFilter) queryParams.append('date', dateFilter);
            if (timeFilter) queryParams.append('time', timeFilter);
            queryParams.append('limit', '10'); // Limit to 10 rows

            fetch(`fetch.php?${queryParams.toString()}`)
                .then(response => {
                    if (response.status === 401) {
                        window.location.href = 'index.php';
                        throw new Error('Session expired');
                    }
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    const tbody = document.getElementById('sensor-data');
                    tbody.innerHTML = '';
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No data found for this date/time. Try a different date or time.</td></tr>';
                        return;
                    }
                    data.forEach(row => {
                        const tempStatus = (row.temperature > 30) ? 'status-danger' : (row.temperature > 25) ? 'status-warning' : 'status-normal';
                        const humidityStatus = (row.humidity > 70) ? 'status-danger' : (row.humidity > 50) ? 'status-warning' : 'status-normal';
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${row.room}</td>
                            <td><span class="${tempStatus}"></span>${row.temperature} °C</td>
                            <td>${row.lux} Lux</td>
                            <td><span class="${humidityStatus}"></span>${row.humidity}%</td>
                            <td>${row.head_count}</td>
                            <td>${formatDateTo24Hour(new Date(row.recorded_at))}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    const tbody = document.getElementById('sensor-data');
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Error fetching data. Please try again later.</td></tr>';
                });
        }

        function startDataFetching() {
            if (fetchInterval) {
                clearInterval(fetchInterval);
            }

            fetchInterval = setInterval(function() {
                const dateFilter = document.querySelector('input[name="date"]').value;
                const timeFilter = document.querySelector('input[name="time"]').value;
                fetchData(dateFilter, timeFilter);
            }, 2000);
        }

        // Handle filter button click
        document.getElementById('filter-form').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent page reload
            const dateFilter = document.querySelector('input[name="date"]').value;
            const timeFilter = document.querySelector('input[name="time"]').value;
            fetchData(dateFilter, timeFilter);
            startDataFetching(); // Restart periodic fetching with new filters
        });

        window.addEventListener('beforeunload', function() {
            if (fetchInterval) {
                clearInterval(fetchInterval);
            }
        });

        window.onload = startDataFetching;
    </script>
</body>

</html>