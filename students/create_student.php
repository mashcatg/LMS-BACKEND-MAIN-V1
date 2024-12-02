<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

include '../check_auth_backend.php';
include '../sms.php';

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

    // Validate and upload image
    $uploadedImageName = null;
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {
        $imageTmpPath = $_FILES['student_image']['tmp_name'];
        $uploadedImageName = basename($_FILES['student_image']['name']);
        $destination = "../uploads/" . $uploadedImageName;

        // Check if the file is an image
        $fileType = mime_content_type($imageTmpPath);
        if (!in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
            echo json_encode(['success' => false, 'message' => 'Only image files (JPG, PNG, GIF) are allowed']);
            exit();
        }

        // Move uploaded file to the desired directory
        if (!move_uploaded_file($imageTmpPath, $destination)) {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
            exit();
        }
    }
$admin_id = $_SESSION['admin_id'];
    // Prepare the rest of the data
    $student_name = $_POST['student_name'] ?? null;
    $student_number = $_POST['student_number'] ?? null;
    $student_institution = $_POST['student_institution'] ?? null;
    $student_date_of_birth = $_POST['student_date_of_birth'] ?? null;
    $father_name = $_POST['father_name'] ?? null;
    $father_number = $_POST['father_number'] ?? null;
    $mother_name = $_POST['mother_name'] ?? null;
    $mother_number = $_POST['mother_number'] ?? null;
    $student_address = $_POST['student_address'] ?? null;
    $service_id = $_SESSION['service_id'];
$student_number = validatePhoneNumber($student_number);
$father_number = validatePhoneNumber($father_number);
$mother_number = validatePhoneNumber($mother_number);
    // Validate phone numbers
    if (empty($student_number) || 
        empty($father_number) || 
        empty($mother_number) || empty($student_name)) {
        echo json_encode(['success' => false, 'message' => 'Student Number, Father Number, Mother Number and student name cannot be blank']);
        exit();
    }

    // Generate a 6-digit alphanumeric password
    $generated_password = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 6)), 0, 6);

    // Hash the password
    $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);

    try {
        // Insert student data into the database
        $stmt = $conn->prepare("INSERT INTO students (student_name, student_number, student_institution, student_date_of_birth, father_name, father_number, mother_name, mother_number, student_address, service_id, student_image, student_password, created_by) VALUES (?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_name, $student_number, $student_institution, $student_date_of_birth, $father_name, $father_number, $mother_name, $mother_number, $student_address, $service_id, $uploadedImageName, $hashed_password, $admin_id]);

        $student_id = $conn->lastInsertId();

        // Fetch service details, including the admin phone number
        $selectService = $conn->prepare("SELECT sub_domain, company_name, ad_phone FROM services WHERE service_id = ? LIMIT 1");
        $selectService->execute([$service_id]);
        $services = $selectService->fetch(PDO::FETCH_ASSOC);
        $subdomain = $services['sub_domain'];
        $company_name = $services['company_name'];
        $ad_phone = $services['ad_phone'];

        // Prepare SMS text
        $sms_text = "Hello $student_name, your student account has been created in $company_name.
        Your password is $generated_password. Please log in to your student portal at $subdomain.ennovat.com using this phone number and password. Change your password to keep your login details secure.
        Thank you.";

        // Send SMS to both the student and the admin
        $receiver_type = 'student';
        $phone_numbers = "$ad_phone, $student_number"; // List of numbers to send the SMS
        $smsResponse = calculateSmsCost($sms_text, $phone_numbers, $receiver_type);
        
        echo json_encode(['success' => true, 'message' => 'Student added successfully', 'student_id' => $student_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding student: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to validate phone numbers
function validatePhoneNumber($number) {
    // Remove any non-numeric characters
    $number = preg_replace('/[^0-9]/', '', $number);
    
    if (strlen($number) === 0) {
        return false; // Empty number
    }

    // Check the prefix and adjust accordingly
    if (substr($number, 0, 4) === '8801' && strlen($number) === 13) {
        return $number; // Valid format, return as is
    } elseif (substr($number, 0, 2) === '01' && strlen($number) === 11) {
        return '880' . substr($number, 1); // Add '88' before the number
    } elseif (substr($number, 0, 1) === '1' && strlen($number) === 10) {
        return '880' . $number; // Add '880' before the number
    } else {
        return false; // Invalid format
    }
}
?>
