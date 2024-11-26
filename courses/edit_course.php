<?php
ini_set('display_errors', 0);
error_reporting(0);

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

    $course_id = $_POST['course_id'];
    $course_name = $_POST['course_name'];
    $course_description = $_POST['course_description'];
    $fee_type = $_POST['fee_type'];
    $course_fee = $_POST['course_fee'];
    $discounted_amount = $_POST['discounted_amount'];
    $active_months = $_POST['active_months'];
    $accepting_admission = $_POST['accepting_admission'];

    // Fetch existing course banner (if any) before checking the file upload
    $sql = "SELECT course_banner FROM courses WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$course_id]);
    $existingCourseBanner = $stmt->fetchColumn();

    // Handle file upload for course banner
    $course_banner = $existingCourseBanner; // Default to the existing banner URL
    if (isset($_FILES['course_banner']) && $_FILES['course_banner']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/course_banners/';
        $file_name = uniqid() . '_' . $_FILES['course_banner']['name'];
        $upload_path = $upload_dir . $file_name;
        $filePathToSave = 'http://lms.ennovat.com/lms-admin/uploads/' . basename($file_name);
        
        if (move_uploaded_file($_FILES['course_banner']['tmp_name'], $upload_path)) {
            $course_banner = $filePathToSave; // Update to the new file URL
        }
    }

    try {
        // Prepare the update query
        $sql = "UPDATE courses SET course_name = ?, course_banner = ?, course_description = ?, fee_type = ?, course_fee = ?, discounted_amount = ?, active_months = ?, accepting_admission = ? WHERE course_id = ?";
        $params = [
            $course_name, 
            $course_banner, // This will either be the new file path or the old one
            $course_description, 
            $fee_type, 
            $course_fee, 
            $discounted_amount, 
            $active_months, 
            $accepting_admission, 
            $course_id
        ];

        // Execute the update query
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating course: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
