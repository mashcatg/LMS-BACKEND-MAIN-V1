<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow cross-origin requests from the frontend
header("Access-Control-Allow-Origin: http://localhost:3000");
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

        // Prepare SQL query to fetch playlist data and associated course names
        $stmt = $conn->prepare("
            SELECT
                p.playlist_id,
                p.playlist_name,
                GROUP_CONCAT(c.course_name SEPARATOR ', ') AS course_names
            FROM
                playlists p
            INNER JOIN
                courses c ON FIND_IN_SET(c.course_id, p.course_id) > 0
            WHERE
                p.service_id = :service_id
            GROUP BY
                p.playlist_id
            ORDER BY
                p.playlist_id DESC
        ");

        // Bind the service_id parameter
        $stmt->execute([':service_id' => $service_id]);

        // Fetch the playlist data
        $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the playlist data as a JSON response
        echo json_encode(['success' => true, 'playlists' => $playlists]);

    } catch (Exception $e) {
        // Handle any errors and return an error message
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // Return an error if the request method is not GET
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
