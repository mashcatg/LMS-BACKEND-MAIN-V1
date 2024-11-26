<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow CORS for local development
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

$service_id = $_SESSION['service_id'];

try {
    // Prepare the SQL query to fetch settings for the current service
    $stmt = $conn->prepare("SELECT * FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return settings as a JSON response with success = true
     echo json_encode(['success' => true, 'settings' => $settings]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching settings: ' . $e->getMessage()]);
}
?>
