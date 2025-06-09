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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLS VDIT Occupants Prediction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        #chatbot-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--accent-color);
            color: #fff;
            font-size: 24px;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
            transition: background 0.3s ease;
            z-index: 1000;
        }
        #chatbot-button:hover {
            background: #3a7abd;
        }
        #chatbot-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            background: #fff;
            color: #000;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            display: none;
            flex-direction: column;
            padding: 15px;
            font-size: 14px;
            z-index: 999;
        }
        #chatbox {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        #chatbox div {
            margin-bottom: 8px;
        }
        #chatbox b {
            color: var(--primary-color);
        }
        #chatbot-container input[type="text"] {
            width: 70%;
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        #chatbot-container button {
            width: 28%;
            padding: 8px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            margin-left: 2%;
        }
        #chatbot-container button:hover {
            background-color: #3a7abd;
        }
        #chatbot-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4d4d;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 16px;
            line-height: 24px;
            text-align: center;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        #chatbot-close:hover {
            background: #e04545;
        }
        #result {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            background-color: #e8f0fe;
            border-radius: 8px;
            padding: 20px;
            min-height: 100px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-top: 20px;
            border: 1px solid var(--accent-color);
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
            #chatbot-container {
                width: 90%;
                right: 5%;
                bottom: 80px;
            }
            #chatbot-button {
                bottom: 10px;
                right: 10px;
            }
            #result {
                font-size: 20px;
                padding: 15px;
                min-height: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="side-panel">
        <h2>KLS VDIT</h2>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-building"></i><span>Dashboard</span></a></li>
            <li><a href="buildings.php"><i class="fas fa-tachometer-alt"></i><span>Building</span></a></li>
            <li><a href="camera.php"><i class="fa fa-video-camera"></i><span>Camera</span></a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-line"></i><span>Analytics</span></a></li>
            <li><a href="occupancy.php" class="active"><i class="fas fa-users"></i><span>Occupants Prediction</span></a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Network for Energy and Internet of Things</h1>
            <div class="logout-link">
                <a href="logout.php">Logout <i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
        <header class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 fw-bold mb-0">OCCUPANTS PREDICTION</h1>
            </div>
        </header>
        <div class="controller-box">
            <h3>Manual Prediction</h3>
            <form id="predictForm">
                <div class="mb-3">
                    <label for="lab" class="form-label">Lab</label>
                    <select id="lab" name="lab" class="form-select" required>
                        <option value="" disabled selected>Select a lab</option>
                        <option value="1">Lab 5</option>
                        <option value="2">Lab 6</option>
                        <option value="3">AI Lab </option>
                        <option value="4">NEW AI Lab </option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="day" class="form-label">Day</label>
                    <select id="day" name="day" class="form-select" required>
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="hour" class="form-label">Hour (0–23)</label>
                    <input type="number" id="hour" name="hour" min="0" max="23" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Predict Occupancy</button>
            </form>
            <div id="result" class="mt-3"></div>
        </div>
        <button id="chatbot-button" onclick="toggleChatbot()">💬</button>
        <div id="chatbot-container">
            <button id="chatbot-close" onclick="toggleChatbot()">×</button>
            <div id="chatbox"></div>
            <div class="d-flex">
                <input type="text" id="userMessage" class="form-control" placeholder="Ask e.g. people in Lab 1 on Friday at 11 AM?">
                <button onclick="sendMessage()" class="btn btn-primary ms-2">Send</button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="occupancy_scripts.js"></script>
    <script>
        AOS.init({
            duration: 800,
            offset: 120,
            once: true
        });
        console.log('Occupancy page loaded');
        function toggleChatbot() {
            console.log('Toggling chatbot');
            const chatbot = document.getElementById('chatbot-container');
            chatbot.style.display = chatbot.style.display === 'none' ? 'flex' : 'none';
        }
    </script>
</body>
</html>