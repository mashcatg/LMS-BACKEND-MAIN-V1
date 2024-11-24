<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE service_id = :service_id");
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the filtered courses
    echo json_encode([
        'courses' => $courses
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
}
?>