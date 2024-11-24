<?php
ini_set('display_errors', 1);
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

    $student_id = $_GET['student_id'] ?? null; 
    if (!$student_id) {
        echo json_encode(['success' => false, 'message' => 'Student ID is missing']);
        exit();
    }
    $student_name = $_POST['student_name'];
    $student_number = $_POST['student_number'];
    $student_institution = $_POST['student_institution'];
    $student_date_of_birth = $_POST['student_date_of_birth'];
    $father_name = $_POST['father_name'];
    $father_number = $_POST['father_number'];
    $mother_name = $_POST['mother_name'];
    $mother_number = $_POST['mother_number'];
    $student_address = $_POST['student_address'];

    // Fetch current image
    $stmt = $conn->prepare("SELECT student_image FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $currentImage = $stmt->fetchColumn();

    // Handle image upload
    $uploadedImageName = $currentImage; // Default to current image
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] == UPLOAD_ERR_OK) {
        $imageTmpPath = $_FILES['student_image']['tmp_name'];
        $uploadedImageName = basename($_FILES['student_image']['name']);
        $destination = "../uploads/" . $uploadedImageName;

        // Move uploaded file to the desired directory
        if (!move_uploaded_file($imageTmpPath, $destination)) {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            exit();
        }
    }

    try {
        $stmt = $conn->prepare("UPDATE students SET student_name = ?, student_number = ?, student_institution = ?, student_date_of_birth = ?, father_name = ?, father_number = ?, mother_name = ?, mother_number = ?, student_address = ?, student_image = ? WHERE student_id = ?");
        $stmt->execute([$student_name, $student_number, $student_institution, $student_date_of_birth, $father_name, $father_number, $mother_name, $mother_number, $student_address, $uploadedImageName, $student_id]);

        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating student: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
