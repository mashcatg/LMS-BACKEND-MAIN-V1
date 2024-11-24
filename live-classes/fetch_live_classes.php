<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow cross-origin requests from the frontend
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

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ensure the user is authenticated (adjust authentication as needed)
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        // Get service_id from the session
        $service_id = $_SESSION['service_id'];

        // Prepare SQL query to fetch live class data for the specific service_id
        $stmt = $conn->prepare("
            SELECT
                lc.live_class_id,
                lc.live_class_name,
                lc.live_class_desc,
                GROUP_CONCAT(DISTINCT c.course_name SEPARATOR ', ') AS course_name,
                GROUP_CONCAT(DISTINCT b.batch_name SEPARATOR ', ') AS batch_name
            FROM
                live_classes lc
            LEFT JOIN
                courses c ON FIND_IN_SET(c.course_id, lc.course_id) > 0
            LEFT JOIN
                batches b ON FIND_IN_SET(b.batch_id, lc.batch_id) > 0
            WHERE
                lc.service_id = :service_id
            GROUP BY
                lc.live_class_id
            ORDER BY
                lc.live_class_id DESC
        ");


        
        // Bind the service_id parameter
        $stmt->execute([':service_id' => $service_id]);

        // Fetch the class data
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the class data as a JSON response
        echo json_encode(['success' => true, 'live_classes' => $classes]);

    } catch (Exception $e) {
        // Handle any errors and return an error message
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // Return an error if the request method is not GET
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
