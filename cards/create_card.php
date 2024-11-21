<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Get the form data
$card_title = $_POST['card_title'] ?? '';
$availability = $_POST['availability'] ?? '';
$course_id = $_POST['course_id'] ?? '';

// Validate form data
if (empty($card_title)) {
    echo json_encode(['success' => false, 'message' => 'Card title is required.']);
    exit();
}

if (empty($course_id)) {
    echo json_encode(['success' => false, 'message' => 'Course ID is required.']);
    exit();
}

if (!isset($_SESSION['service_id']) || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

// Convert availability to a string value
$availability = ($availability === '1' || $availability === 'yes') ? 'yes' : 'no';

$service_id = $_SESSION['service_id'];
$created_by = $_SESSION['admin_id'];
$time = date('Y-m-d H:i:s');

try {
    // Prepare and execute the insert statement
    $stmt = $conn->prepare("INSERT INTO cards (card_title, availability, course_id, service_id, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$card_title, $availability, $course_id, $service_id, $created_by, $time]);

    // Return success response with the newly created card
    echo json_encode([
        'success' => true,
        'message' => 'Card added successfully.',
        'card' => [
            'card_id' => $conn->lastInsertId(),
            'card_title' => $card_title,
            'availability' => $availability,
            'course_id' => $course_id,
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating card: ' . $e->getMessage()]);
}
?>
