<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include '../db.php';

// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

try {
    // Prepare the SQL query to fetch materials and course names related to the current service
    $stmt = $conn->prepare("
        SELECT materials.*, courses.course_name 
        FROM materials
        INNER JOIN courses ON materials.course_id = courses.course_id
        WHERE materials.service_id = ?
    ");
    $stmt->execute([$service_id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return materials as a JSON response, including course_name
    echo json_encode(['materials' => $materials]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching materials: ' . $e->getMessage()]);
}
?>