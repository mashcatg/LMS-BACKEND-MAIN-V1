<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'] ;

try {
    $stmt = $conn->prepare("SELECT * FROM routine WHERE service_id = :service_id");
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->execute();
    $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the filtered routines
    echo json_encode([
        'routines' => $routines
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching routines: ' . $e->getMessage()]);
}
?>
