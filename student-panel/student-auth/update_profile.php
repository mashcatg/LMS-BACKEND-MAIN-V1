<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow cross-origin requests from your frontend app
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Include authentication check
include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Get student_id and service_id from session
$student_id = $_SESSION['student_id'] ?? '1';
$service_id = $_SESSION['service_id'] ?? '61545';

if (!$student_id || !$service_id) {
    echo json_encode(['error' => 'Invalid session']);
    exit();
}

// Define the uploads directory
$uploads_dir = '../../uploads/';
$max_file_size = 2 * 1024 * 1024; // 2MB limit for image uploads

// Read JSON input
$input_data = json_decode(file_get_contents('php://input'), true);

// Get POST data from the request
$student_name = $input_data['student_name'];
$student_image = $input_data['student_image']; // Image as base64 string
$student_institution = $input_data['student_institution'];
$student_address = $input_data['student_address'];
$student_date_of_birth = $input_data['student_date_of_birth'];
$father_name = $input_data['father_name']; // Father Name (editable)
$mother_name = $input_data['mother_name']; // Mother Name (editable)

// Prepare SQL to fetch the current profile image and other data
$stmt = $conn->prepare("SELECT student_image, father_name, mother_name FROM students WHERE student_id = :student_id AND service_id = :service_id");
$stmt->execute([':student_id' => $student_id, ':service_id' => $service_id]);
$currentProfile = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if student exists
if (!$currentProfile) {
    echo json_encode(['error' => 'Profile not found']);
    exit();
}

// Retain the old image if no new image is provided
if (empty($student_image)) {
    $student_image = $currentProfile['student_image']; // Keep the existing image
} else {
    // Process the new image if provided (base64 to actual image)
    $imageData = base64_decode($student_image);
    
    // Create a unique name for the image to avoid overwriting
    $image_name = uniqid('profile_', true) . '.jpg';
    $image_path = $uploads_dir . $image_name;
    
    // Check if image is valid and within size limit
    if (strlen($imageData) > $max_file_size) {
        echo json_encode(['error' => 'File size exceeds the 2MB limit']);
        exit();
    }
    
    // Save the new image to the server
    if (!file_put_contents($image_path, $imageData)) {
        echo json_encode(['error' => 'Failed to upload image']);
        exit();
    }
    
    // Set the path to the uploaded image in the database
    $student_image = $image_name;
}

try {
    // Prepare SQL to update the student's profile
    $stmt = $conn->prepare("
        UPDATE students 
        SET 
            student_name = :student_name, 
            student_image = :student_image, 
            student_institution = :student_institution, 
            student_address = :student_address, 
            student_date_of_birth = :student_date_of_birth,
            father_name = :father_name, 
            mother_name = :mother_name
        WHERE student_id = :student_id AND service_id = :service_id
    ");

    $stmt->execute([
        ':student_name' => $student_name,
        ':student_image' => $student_image,
        ':student_institution' => $student_institution,
        ':student_address' => $student_address,
        ':student_date_of_birth' => $student_date_of_birth,
        ':father_name' => $father_name, // Only update the father name
        ':mother_name' => $mother_name, // Only update the mother name
        ':student_id' => $student_id,
        ':service_id' => $service_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
