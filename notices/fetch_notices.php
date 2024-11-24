<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];

        // Fetch notices related to the service_id
        $stmt = $conn->prepare("SELECT * FROM notices WHERE service_id = :service_id ORDER BY notice_time DESC");
        $stmt->execute([':service_id' => $service_id]);
        $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notices as &$notice) {
            if ($notice['notice_type'] !== 'Public') {
                $notice['notice_type'] = 'Filtered';
            }
        }
        
        echo json_encode(['success' => true, 'notices' => $notices]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
