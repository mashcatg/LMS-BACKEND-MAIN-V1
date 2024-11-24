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

        // Fetch all note tags
        $stmt = $conn->prepare("
            SELECT notes.note_tags
            FROM notes
            WHERE notes.service_id = :service_id
        ");
        $stmt->execute([':service_id' => $service_id]);

        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Collect all tags, split by delimiter (assuming commas), and get unique tags
        $allTags = [];
        foreach ($notes as $note) {
            if (!empty($note['note_tags'])) {
                $tags = explode(',', $note['note_tags']); // Assuming tags are comma-separated
                $allTags = array_merge($allTags, $tags);
            }
        }

        // Remove duplicates and trim any extra spaces
        $uniqueTags = array_unique(array_map('trim', $allTags));

        echo json_encode(['success' => true, 'tags' => $uniqueTags]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
