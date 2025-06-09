<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLS VDIT Smart Analytics Dashboard</title>
    
    <!-- Frameworks -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Chart.js for visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Side Panel Styles */
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

        /* Main Content Styles */
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

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
            padding: 20px;
            height: 350px; /* Fixed height for consistency */
        }
        
        .chart-container:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .chart-canvas {
            width: 100%;
            height: 250px; /* Adjusted height to fit within container */
        }
        
        /* Dashboard Controls */
        .dashboard-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .dashboard-filter {
            flex: 1;
            min-width: 200px;
        }
        
        /* Info Boxes */
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
            margin-bottom: 0.5rem;
        }
        
        .info-change {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .info-change.up {
            color: #28a745;
        }
        
        .info-change.down {
            color: #dc3545;
        }
        
        /* Loading State */
        .loading-state {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 300px;
            background: white;
            border-radius: 15px;
        }
        
        .spinner {
            width: 3rem;
            height: 3rem;
            color: var(--accent-color);
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

        /* Responsive Styles */
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
            
            .chart-canvas {
                height: 200px;
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
            
            .chart-canvas {
                height: 150px;
            }
        }

        @media (max-width: 576px) {
            .header h1 {
                font-size: 1.5rem;
            }
            
            .info-value {
                font-size: 1.5rem;
            }
            
            .chart-canvas {
                height: 120px;
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
            </ personally-ownedli>
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
                <a href="analytics.php" class="active">
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
            <h1>Network for Environmental Monitoring</h1>
            <div class="logout-link">
                <a href="logout.php">Logout <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="dashboard-header">
            <h3><i class="fas fa-chart-bar"></i> Environmental Analytics Dashboard</h3>
            <p class="text-muted">View real-time and historical environmental data</p>
            <p class="text-muted">Data for: <span id="selected-date"><?php echo date('Y-m-d'); ?></span></p>
            
            <div class="dashboard-controls mt-3">
                <div class="dashboard-filter">
                    <label for="filter-type" class="form-label">Filter Type</label>
                    <select class="form-select" id="filter-type">
                        <option value="date" selected>Date</option>
                        <!-- <option value="time">Time</option>
                        <option value="datetime">Date & Time</option> -->
                    </select>
                </div>
                <div class="dashboard-filter">
                    <label for="filter-date" class="form-label">Select Date</label>
                    <input type="date" class="form-control" id="filter-date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <!-- <div class="dashboard-filter">
                    <label for="filter-time" class="form-label">Select Time</label>
                    <input type="time" class="form-control" id="filter-time">
                </div> -->
            </div>
        </div>

        <!-- Chart Containers (Side by Side) -->
        <div class="row">
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="chart-container">
                    <h5>Temperature (°C)</h5>
                    <canvas id="temperatureChart" class="chart-canvas"></canvas>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="chart-container">
                    <h5>Humidity (%)</h5>
                    <canvas id="humidityChart" class="chart-canvas"></canvas>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="chart-container">
                    <h5>Luminosity (lux)</h5>
                    <canvas id="luminosityChart" class="chart-canvas"></canvas>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="chart-container">
                    <h5>Head Count</h5>
                    <canvas id="headCountChart" class="chart-canvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Highest Recorded Values -->
        <div class="row">
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="info-box">
                    <div class="info-title">Highest Temperature Recorded</div>
                    <div class="info-value" id="highest-temperature">0.0 °C</div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 mb-4">
                <div class="info-box">
                    <div class="info-title">Highest Humidity Recorded</div>
                    <div class="info-value" id="highest-humidity">0.0 %</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS animations
        AOS.init();

        // Initialize Chart.js
        const temperatureChart = new Chart(document.getElementById('temperatureChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Temperature (°C)',
                    data: [],
                    borderColor: 'rgba(74, 144, 226, 1)',
                    backgroundColor: 'rgba(74, 144, 226, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        title: { display: true, text: 'Time' },
                        ticks: {
                            maxTicksLimit: 12, // Limit number of ticks for readability
                            callback: function(value, index, values) {
                                return this.getLabelForValue(value); // Ensure labels are shown
                            }
                        }
                    },
                    y: { title: { display: true, text: 'Temperature (°C)' } }
                }
            }
        });

        const humidityChart = new Chart(document.getElementById('humidityChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Humidity (%)',
                    data: [],
                    borderColor: 'rgba(40, 167, 69, 1)',
                    backgroundColor: 'rgba(40, 167, 69, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        title: { display: true, text: 'Time' },
                        ticks: {
                            maxTicksLimit: 12,
                            callback: function(value, index, values) {
                                return this.getLabelForValue(value);
                            }
                        }
                    },
                    y: { title: { display: true, text: 'Humidity (%)' } }
                }
            }
        });

        const luminosityChart = new Chart(document.getElementById('luminosityChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Luminosity (lux)',
                    data: [],
                    borderColor: 'rgba(220, 53, 69, 1)',
                    backgroundColor: 'rgba(220, 53, 69, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        title: { display: true, text: 'Time' },
                        ticks: {
                            maxTicksLimit: 12,
                            callback: function(value, index, values) {
                                return this.getLabelForValue(value);
                            }
                        }
                    },
                    y: { title: { display: true, text: 'Luminosity (lux)' } }
                }
            }
        });

        const headCountChart = new Chart(document.getElementById('headCountChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Head Count',
                    data: [],
                    borderColor: 'rgba(255, 193, 7, 1)',
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        title: { display: true, text: 'Time' },
                        ticks: {
                            maxTicksLimit: 12,
                            callback: function(value, index, values) {
                                return this.getLabelForValue(value);
                            }
                        }
                    },
                    y: { title: { display: true, text: 'Head Count' } }
                }
            }
        });

        // ThingSpeak configuration
        const channelId = '2927250'; // Replace with your ThingSpeak channel ID
        const apiKey = '3HBH0XDXP6CJHOO2'; // Replace with your ThingSpeak Read API Key
        const baseUrl = `https://api.thingspeak.com/channels/${channelId}/feeds.json`;

        // WHO standards
        const WHO_MAX_TEMPERATURE = 24; // °C
        const WHO_MAX_HUMIDITY = 50; // %

        // Variables to track highest recorded values
        let highestTemperature = 0;
        let highestHumidity = 0;

        // Function to calculate dynamic Y-axis bounds
        function getAxisBounds(data, isInteger = false) {
            if (!data || data.length === 0) return { min: 0, max: 100 };
            const values = data.filter(val => val !== null && !isNaN(val));
            if (values.length === 0) return { min: 0, max: 100 };
            const min = Math.min(...values);
            const max = Math.max(...values);
            const range = max - min;
            const padding = range * 0.1 || 1; // 10% padding, minimum 1
            return {
                min: isInteger ? Math.floor(min - padding) : min - padding,
                max: isInteger ? Math.ceil(max + padding) : max + padding
            };
        }

        // Function to update highest value colors based on WHO standards
        function updateHighestValueColors() {
            const tempElement = document.getElementById('highest-temperature');
            const humidityElement = document.getElementById('highest-humidity');

            // Update temperature color
            tempElement.classList.remove('text-success', 'text-danger');
            tempElement.classList.add(highestTemperature > WHO_MAX_TEMPERATURE ? 'text-danger' : 'text-success');

            // Update humidity color
            humidityElement.classList.remove('text-success', 'text-danger');
            humidityElement.classList.add(highestHumidity > WHO_MAX_HUMIDITY ? 'text-danger' : 'text-success');
        }

        // Function to fetch and update data from ThingSpeak
        async function updateDashboard() {
            const filterType = document.getElementById('filter-type').value;
            const filterDate = document.getElementById('filter-date').value;
            const filterTime = document.getElementById('filter-time')?.value;

            // Update selected date display
            document.getElementById('selected-date').textContent = filterDate;

            let queryParams = `?api_key=${apiKey}&results=50`; // Default to last 50 entries

            // Adjust query parameters based on filter type
            if (filterType === 'date' && filterDate) {
                // Fetch data for the entire selected date
                queryParams = `?api_key=${apiKey}&start=${filterDate}T00:00:00Z&end=${filterDate}T23:59:59Z`;
            } else if (filterType === 'time' && filterTime) {
                // Fetch data for today at the selected time
                const today = new Date().toISOString().split('T')[0];
                const [hours, minutes] = filterTime.split(':');
                const startTime = `${today}T${hours}:${minutes}:00Z`;
                const endTime = `${today}T${hours}:${minutes}:59Z`;
                queryParams = `?api_key=${apiKey}&start=${startTime}&end=${endTime}`;
            } else if (filterType === 'datetime' && filterDate && filterTime) {
                // Fetch data for the specific date and time
                const [hours, minutes] = filterTime.split(':');
                const startTime = `${filterDate}T${hours}:${minutes}:00Z`;
                const endTime = `${filterDate}T${hours}:${minutes}:59Z`;
                queryParams = `?api_key=${apiKey}&start=${startTime}&end=${endTime}`;
            }

            try {
                const response = await fetch(`${baseUrl}${queryParams}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const json = await response.json();
                const data = json.feeds;

                // Update charts
                const labels = data.map(item => {
                    const date = new Date(item.created_at);
                    // Always show time in HH:MM format for day-wise data
                    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                });
                const temperatures = data.map(item => parseFloat(item.field1) || 0); // field1: temperature
                const humidities = data.map(item => parseFloat(item.field2) || 0);   // field2: humidity
                const luminosities = data.map(item => parseFloat(item.field3) || 0); // field3: luminosity
                const headCounts = data.map(item => parseInt(item.field4) || 0);    // field4: head count

                // Update highest recorded values
                const maxTemperature = Math.max(...temperatures);
                const maxHumidity = Math.max(...humidities);
                if (maxTemperature > highestTemperature) {
                    highestTemperature = maxTemperature;
                    document.getElementById('highest-temperature').textContent = `${highestTemperature.toFixed(1)} °C`;
                }
                if (maxHumidity > highestHumidity) {
                    highestHumidity = maxHumidity;
                    document.getElementById('highest-humidity').textContent = `${highestHumidity.toFixed(1)} %`;
                }

                // Update colors based on WHO standards
                updateHighestValueColors();

                // Update Temperature Chart
                const tempBounds = getAxisBounds(temperatures);
                temperatureChart.data.labels = labels;
                temperatureChart.data.datasets[0].data = temperatures;
                temperatureChart.options.scales.y.min = tempBounds.min;
                temperatureChart.options.scales.y.max = tempBounds.max;
                temperatureChart.options.scales.x.title.text = 'Time';
                temperatureChart.update();

                // Update Humidity Chart
                const humidityBounds = getAxisBounds(humidities);
                humidityChart.data.labels = labels;
                humidityChart.data.datasets[0].data = humidities;
                humidityChart.options.scales.y.min = humidityBounds.min;
                humidityChart.options.scales.y.max = humidityBounds.max;
                humidityChart.options.scales.x.title.text = 'Time';
                humidityChart.update();

                // Update Luminosity Chart
                const luminosityBounds = getAxisBounds(luminosities);
                luminosityChart.data.labels = labels;
                luminosityChart.data.datasets[0].data = luminosities;
                luminosityChart.options.scales.y.min = luminosityBounds.min;
                luminosityChart.options.scales.y.max = luminosityBounds.max;
                luminosityChart.options.scales.x.title.text = 'Time';
                luminosityChart.update();

                // Update Head Count Chart
                const headCountBounds = getAxisBounds(headCounts, true);
                headCountChart.data.labels = labels;
                headCountChart.data.datasets[0].data = headCounts;
                headCountChart.options.scales.y.min = headCountBounds.min;
                headCountChart.options.scales.y.max = headCountBounds.max;
                headCountChart.options.scales.x.title.text = 'Time';
                headCountChart.update();
            } catch (error) {
                console.error('Error fetching data:', error);
                document.getElementById('highest-temperature').textContent = 'Error';
                document.getElementById('highest-humidity').textContent = 'Error';
                document.getElementById('highest-temperature').classList.remove('text-success', 'text-danger');
                document.getElementById('highest-humidity').classList.remove('text-success', 'text-danger');
            }
        }

        // Initial data fetch
        updateDashboard();

        // Real-time updates every 15 seconds
        setInterval(updateDashboard, 15000);

        // Event listeners for filter changes
        document.getElementById('filter-type').addEventListener('change', updateDashboard);
        document.getElementById('filter-date').addEventListener('change', updateDashboard);
        if (document.getElementById('filter-time')) {
            document.getElementById('filter-time').addEventListener('change', updateDashboard);
        }
    </script>
</body>
</html>