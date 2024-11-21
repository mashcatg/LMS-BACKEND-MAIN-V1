<?php
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $material_id = $_POST['material_id'];
    $material_name = $_POST['material_name'];
    $course_id = $_POST['course_id'];

    try {
        $stmt = $conn->prepare("UPDATE materials SET material_name = ?, course_id = ? WHERE material_id = ?");
        $stmt->execute([$material_name, $course_id, $material_id]);

        $stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        $updated_material = [
            'material_id' => $material_id,
            'material_name' => $material_name,
            'course_id' => $course_id,
            'course_name' => $course['course_name']
        ];

        echo json_encode(['success' => true, 'message' => 'Material updated successfully', 'material' => $updated_material]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating material: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}