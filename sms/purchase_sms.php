<?php
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $service_id = $_SESSION['service_id'] ?? null;
    $smsNumber = $_POST['smsNumber'] ?? null;
    $admin_id = $_SESSION['admin_id'];
    $sms_rate = 0.33;
    if (!$smsNumber) {
        echo json_encode(['success' => false, 'message' => 'SMS Amount is required']);
        exit();
    }
    try {
        $stmt = $conn->prepare("INSERT INTO sms_transactions (sms_rate, sms_amount, created_at, created_by, service_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sms_rate, $smsNumber, date('Y-m-d H:i:s'), $admin_id, $service_id]);
        // update sms credit 
        $updateServices = $conn->prepare("UPDATE services SET sms_credit = sms_credit + ? WHERE service_id = ?");
        $updateServices->execute([$smsNumber, $service_id]);

        echo json_encode(['success' => true, 'message' => 'SMS Purchased successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding sms: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}