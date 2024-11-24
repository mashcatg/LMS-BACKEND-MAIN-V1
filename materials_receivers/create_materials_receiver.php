<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
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

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $material_id = $data['material_id'] ?? null;
    $student_index = $data['student_index'] ?? null;
    $service_id = $_SESSION['service_id'] ?? null;

    if (!$material_id || !$student_index || !$service_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    try {
        // Get enrollment_id from student_index
        $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_index = ?");
        $stmt->execute([$student_index]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrollment) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit();
        }

        $enrollment_id = $enrollment['enrollment_id'];

        // Check if the receiver already exists
        $stmt = $conn->prepare("SELECT material_receiver_id FROM material_receivers WHERE material_id = ? AND enrollment_id = ?");
        $stmt->execute([$material_id, $enrollment_id]);
        $existing_receiver = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_receiver) {
            echo json_encode(['success' => false, 'message' => 'Student already marked as received material']);
            exit();
        }

        // Insert new receiver
        $stmt = $conn->prepare("INSERT INTO material_receivers (enrollment_id, material_id, service_id) VALUES (?, ?, ?)");
        $stmt->execute([$enrollment_id, $material_id, $service_id]);

        echo json_encode(['success' => true, 'message' => 'Material receiver added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding material receiver: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
