<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header("Access-Control-Allow-Origin: http://localhost:3000");
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
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $service_id = $_SESSION['service_id'];
    $playlist_id = $_POST['id'] ?? null;
    $playlist_name = $_POST['playlist_name'] ?? null;
    $course_ids = $_POST['course_id'] ?? null;

    if (!$playlist_id || !$playlist_name || !$course_ids) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing input']);
        exit();
    }

    try {
        $stmt = $conn->prepare("UPDATE playlists SET playlist_name = :playlist_name, course_id = :course_id WHERE playlist_id = :playlist_id AND service_id = :service_id");
        $stmt->execute([
            ':playlist_name' => $playlist_name,
            ':course_id' => $course_ids,
            ':playlist_id' => $playlist_id,
            ':service_id' => $service_id,
        ]);

        echo json_encode(['success' => true, 'message' => 'Playlist updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>