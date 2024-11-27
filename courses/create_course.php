<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
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
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Get the form data
$course_name = $_POST['course_name'] ?? '';
$course_description = $_POST['course_description'] ?? '';
$fee_type = $_POST['fee_type'] ?? '';
$course_fee = $_POST['course_fee'] ?? '';
$discounted_amount = $_POST['discounted_amount'] ?? null;  // Can be null if not provided
$active_months = $_POST['active_months'] ?? '';  // Months as a comma-separated string
$accepting_admission = isset($_POST['accepting_admission']) && $_POST['accepting_admission'] === 'Yes' ? 'Yes' : 'No';

$file_address = null; // Initialize variable
if (isset($_FILES['course_banner'])) {
    if ($_FILES['course_banner']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['course_banner']['tmp_name'];
        $file_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '', basename($_FILES['course_banner']['name'])); // Sanitize filename
        $file_store_path = "../uploads/" . $file_name; // Change this path as needed
        $filePathToSave = 'http://lms.ennovat.com/lms-admin/uploads/'.basename($file_name);
        // Move the uploaded file to the desired directory
        if (move_uploaded_file($file_tmp_path, $file_store_path)) {
            $file_address = $file_store_path; // Save the file address
        } else {
            echo json_encode(['success' => false, 'message' => 'File upload failed']);
            exit(); // Stop execution on failure
        }
    } else {
        // Handle specific upload error
        $error_code = $_FILES['course_banner']['error'];
        echo json_encode(['success' => false, 'message' => 'File upload error: ' . $error_code]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

// Check if all required fields are filled
if (empty($course_name) || empty($course_description) || empty($course_fee) || $file_address === null) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

if (!isset($_SESSION['service_id']) || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$service_id = $_SESSION['service_id'];
$time = date('Y-m-d H:i:s');  // Current timestamp for "time"

try {
    // Insert the new course into the database
    $stmt = $conn->prepare("INSERT INTO courses (course_name, course_banner, course_description, fee_type, course_fee, discounted_amount, active_months, accepting_admission, service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$course_name, $filePathToSave, $course_description, $fee_type, $course_fee, $discounted_amount, $active_months, $accepting_admission, $service_id]);

    // Return success response with the newly created course
    echo json_encode([
        'success' => true,
        'course' => []
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating course: ' . $e->getMessage()]);
}
?>
