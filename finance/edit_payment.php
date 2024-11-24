<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$payment_id = $data['payment_id'] ?? null;
$discounted_amount = $data['discounted_amount'] ?? null;
$paid_amount = $data['paid_amount'] ?? null;

if (!$payment_id || !$discounted_amount || !$paid_amount) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Get service_id from session
    $service_id = $_SESSION['service_id'];
    

    // Update payment record
    $stmt = $conn->prepare("UPDATE payments SET discounted_amount = ?, paid_amount = ? WHERE payment_id = ? AND service_id = ?");
    $stmt->execute([$discounted_amount, $paid_amount, $payment_id, $service_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $payment_id]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating payment: ' . $e->getMessage()]);
}
?>
