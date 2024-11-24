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

// Get the auth token from the cookie
$auth_token = $_COOKIE['student_auth'];
$service_id = $_COOKIE['service_id'];
if (!isset($auth_token)) {
    echo json_encode(['success' => false, 'message' => $auth_token]);
    exit();
}

try {
    // Check if the auth token exists and is still valid
    $stmt = $conn->prepare("SELECT student_id, expiry_date FROM student_logins WHERE auth_token = ? AND expiry_date > NOW() ORDER BY login_id DESC LIMIT 1");
    $stmt->execute([$auth_token]);
    $login = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$login) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Authenticated successfully', 'student_id' => $login['student_id']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking authentication: ' . $e->getMessage()]);
}
?>
