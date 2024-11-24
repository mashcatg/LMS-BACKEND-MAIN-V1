<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check authentication
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

$card_id = $_POST['card_id'] ?? '';
$card_title = $_POST['card_title'] ?? '';
$availability = $_POST['availability'] ?? '';
$course_id = $_POST['course_id'] ?? '';

if (empty($card_title) || empty($card_id)) {
    echo json_encode(['success' => false, 'message' => 'Card title and ID are required.']);
    exit();
}

$availability = ($availability === '1' || $availability === 'yes') ? 'yes' : 'no';

try {
    $stmt = $conn->prepare("UPDATE cards SET card_title = ?, availability = ?, course_id = ?WHERE card_id = ?");
    $stmt->execute([$card_title, $availability, $course_id, $card_id]);

    echo json_encode(['success' => true, 'message' => 'Card updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating card: ' . $e->getMessage()]);
}
}
?>
