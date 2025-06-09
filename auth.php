<?php
session_start();
include 'db.php';

// Check if database connection is successful
if ($conn->connect_error) {
    $_SESSION['error'] = "Database connection failed.";
    error_log("Database connection failed: " . $conn->connect_error);
    header("Location: index.php");
    exit();
}

// Handle navigation actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'show_forgot_form') {
        $_SESSION['show_forgot_form'] = true;
        unset($_SESSION['email_verified']);
        unset($_SESSION['email']);
        header("Location: index.php");
        exit();
    } elseif ($_GET['action'] === 'show_login_form') {
        unset($_SESSION['show_forgot_form']);
        unset($_SESSION['email_verified']);
        unset($_SESSION['email']);
        header("Location: index.php");
        exit();
    }
}

// Handle Check Email for Forgot Password
if (isset($_POST['check_email'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: index.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error.";
        error_log("Prepare failed: " . $conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['email_verified'] = true;
        $_SESSION['email'] = $email;
        $_SESSION['show_forgot_form'] = true;
        $_SESSION['success'] = "Email verified. Please enter your new password.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "No account found with that email.";
        header("Location: index.php");
        exit();
    }
    $stmt->close();
}

// Handle Change Password
if (isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    $email = $_SESSION['email'] ?? '';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Session expired or invalid email.";
        unset($_SESSION['show_forgot_form']);
        unset($_SESSION['email_verified']);
        unset($_SESSION['email']);
        header("Location: index.php");
        exit();
    }
    if ($new_password !== $confirm_new_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: index.php");
        exit();
    }
    if (strlen($new_password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        header("Location: index.php");
        exit();
    }
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error.";
        error_log("Prepare failed: " . $conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("ss", $hashed_password, $email);
    if ($stmt->execute()) {
        unset($_SESSION['show_forgot_form']);
        unset($_SESSION['email_verified']);
        unset($_SESSION['email']);
        $_SESSION['success'] = "Password changed successfully! Please login.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Error changing password.";
        error_log("Error changing password: " . $stmt->error);
        header("Location: index.php");
        exit();
    }
    $stmt->close();
}

// Handle Signup
if (isset($_POST['signup'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    if (!$email) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: index.php");
        exit();
    }
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: index.php");
        exit();
    }
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        header("Location: index.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error.";
        error_log("Prepare failed: " . $conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists.";
        header("Location: index.php");
        exit();
    }
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    if (!$stmt) {
        $_SESSION['error'] = "Database error.";
        error_log("Prepare failed: " . $conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("ss", $email, $hashed_password);
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        $room_name = "Living Room";
        $temperature = 25;
        $humidity = 8;
        $stmt = $conn->prepare("INSERT INTO rooms (user_id, name, temperature, humidity) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Database error.";
            error_log("Prepare failed: " . $conn->error);
            header("Location: index.php");
            exit();
        }
        $stmt->bind_param("isii", $user_id, $room_name, $temperature, $humidity);
        $stmt->execute();
        $room_id = $conn->insert_id;
        $devices = [
            ['name' => 'Air Conditioner', 'type' => 'switch', 'status' => 'On'],
            ['name' => 'Television', 'type' => 'switch', 'status' => 'On'],
            ['name' => 'Door Lock', 'type' => 'lock', 'status' => 'UNLOCKED'],
            ['name' => 'Curtain', 'type' => 'curtain', 'status' => 'CLOSED'],
            ['name' => 'Windows', 'type' => 'window', 'status' => 'Closed']
        ];
        $stmt = $conn->prepare("INSERT INTO devices (room_id, name, type, status) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Database error.";
            error_log("Prepare failed: " . $conn->error);
            header("Location: index.php");
            exit();
        }
        foreach ($devices as $device) {
            $stmt->bind_param("isss", $room_id, $device['name'], $device['type'], $device['status']);
            $stmt->execute();
        }
        $station = "JAZZ VIBES";
        $volume = 60;
        $playing = 1;
        $bluetooth = 0;
        $stmt = $conn->prepare("INSERT INTO media_settings (user_id, station, volume, playing, bluetooth_connected) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Database error.";
            error_log("Prepare failed: " . $conn->error);
            header("Location: index.php");
            exit();
        }
        $stmt->bind_param("isiii", $user_id, $station, $volume, $playing, $bluetooth);
        $stmt->execute();
        $_SESSION['success'] = "Successfully registered! You can now login.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Registration failed.";
        error_log("Error: " . $stmt->error);
        header("Location: index.php");
        exit();
    }
    $stmt->close();
}

// Handle Login
if (isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    if (!$email) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: index.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Database error.";
        error_log("Prepare failed: " . $conn->error);
        header("Location: index.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user['email'];
            $_SESSION['success'] = "Login successful!";
            header("Location: dashboard.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "User not found.";
        header("Location: index.php");
        exit();
    }
    $stmt->close();
}

$conn->close();
?>