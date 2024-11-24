<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include 'db.php';

// Check if admin_token exists in cookies
$auth_token = $_COOKIE['admin_token'] ?? '';

if (empty($auth_token)) {
    $checkAuthMessage = 'Not authenticated';
    exit();
}

try {
    // Validate the auth token and check expiry
    $stmt = $conn->prepare("
        SELECT admin_id, service_id, expiry_date 
        FROM admin_logins 
        WHERE admin_token = ? AND expiry_date > NOW()
    ");
    $stmt->execute([$auth_token]);
    $login = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$login) {
        $checkAuthMessage = 'Invalid Session';
        exit();
    }

    // Authentication success
    $_SESSION['admin_id'] = $login['admin_id'];
    $_SESSION['service_id'] = $login['service_id'];

    // Fetch admin_name from admins table
    $stmt = $conn->prepare("SELECT admin_name FROM admins WHERE admin_id = ?");
    $stmt->execute([$login['admin_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        $_SESSION['admin_name'] = $admin['admin_name'];
    }

    $checkAuthMessage = 'success';
} catch (Exception $e) {
    $checkAuthMessage = 'Error checking authentication: ' . $e->getMessage();
    exit();
}
