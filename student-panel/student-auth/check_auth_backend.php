<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include '../../db.php';

$auth_token = $_COOKIE['student_auth'];

if (empty($auth_token)) {
    $checkAuthMessage = 'Not authenticated';
    exit();
}

try {
    // Check if the auth token exists and is still valid
    $stmt = $conn->prepare("
        SELECT student_id, service_id, expiry_date 
        FROM student_logins 
        WHERE auth_token = ? AND expiry_date > NOW()
        LIMIT 1
    ");
    $stmt->execute([$auth_token]);
    $login = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$login) {
        $checkAuthMessage = 'Invalid Session';
        exit();
    }
    $checkAuthMessage = 'success';
    $_SESSION['student_id'] = $login['student_id'];
    $_SESSION['service_id'] = $login['service_id'];

} catch (Exception $e) {
    $checkAuthMessage = 'Error checking authentication: ' . $e->getMessage();
}
