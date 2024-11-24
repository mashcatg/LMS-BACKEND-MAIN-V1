<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
exit(0);
}

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Check authentication
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $service_id = $_SESSION['service_id'];
    $attendance_id = $_GET['attendance_id'] ?? null;

    // Validate that attendance_id and playlist_id are provided
    if (!$attendance_id) {
        echo json_encode(['success' => false, 'message' => 'Notice ID is required']);
        exit();
    }

    try {
        // Prepare the SQL statement
        $sql = "DELETE FROM attendance WHERE attendance_id = :attendance_id AND service_id = :service_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':attendance_id', $attendance_id, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);

        // Execute the query and check if successful
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notice deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete Notice']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
