<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set up CORS headers for your frontend
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
session_set_cookie_params([
    'lifetime' => 180 * 24 * 60 * 60, // 180 days
    'path' => '/',                     // Available site-wide
    'domain' => '.youthsthought.com',   // Valid for subdomains of youthsthought.com
    'secure' => true,                  // Secure only over HTTPS
    'httponly' => true,                // Make the cookie inaccessible to JavaScript
    'samesite' => 'None',              // Allow cross-site cookie usage
]);
// Start the session
session_start();
include '../../db.php';

// Read JSON body from the request
$input = json_decode(file_get_contents('php://input'), true);
$student_number = $input['phone'] ?? null;
$student_password = $input['password'] ?? null;
$service_id = $_COOKIE['service_id'];

// Validate session and input
if (empty($service_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid URL: service_id not set in session', 'session' => $_SESSION]);
    exit();
}

if (empty($student_number) || empty($student_password)) {
    echo json_encode(['success' => false, 'message' => 'Student phone number or password is missing.']);
    exit();
}

try {
    // Check if the student exists in the database
    $stmt = $conn->prepare("SELECT student_id, student_password, service_id FROM students WHERE student_number = ? AND service_id = ?");
    $stmt->execute([$student_number, $service_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or password.']);
        exit();
    }

    // Check if the student has a password set
    if (empty($student['student_password'])) {
        echo json_encode(['success' => false, 'message' => 'Password not set for this account.']);
        exit();
    }

    // Verify the password
    if (!password_verify($student_password, $student['student_password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or password.']);
        exit();
    }

    // Generate a unique authentication token
    $auth_token = bin2hex(random_bytes(32));

    // Set the expiry date for 6 months later
    $expiry_date = date('Y-m-d H:i:s', strtotime('+6 months'));

    // Store the auth token in the database
    $stmt = $conn->prepare("
        INSERT INTO student_logins (student_id, auth_token, expiry_date, service_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$student['student_id'], $auth_token, $expiry_date, $student['service_id']]);

    // Set cookies for the student authentication token
    setcookie('student_auth', $auth_token['service_id'], [
            'expires' => time() + (180 * 24 * 60 * 60),  // 180 days
            'path' => '/',                              // Available site-wide
            'domain' => '.youthsthought.com',            // Valid for subdomains of youthsthought.com
            'secure' => true,                           // Secure only over HTTPS
            'httponly' => true,                         // Make it inaccessible to JavaScript
            'samesite' => 'None'                        // Allow cross-site cookie usage
        ]);
    setcookie('student_auth', $auth_token, time() + (180 * 24 * 60 * 60), '/', 'localhost', false, true);
    
    // Fetch additional enrollment information
    $stmt = $conn->prepare("
        SELECT enrollment_id, student_index FROM enrollments WHERE service_id = ? AND student_id = ?
    ");
    $stmt->execute([$service_id, $student['student_id']]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Store student and session details
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['enrollment_id'] = $enrollment['enrollment_id'] ?? null;
    $_SESSION['student_index'] = $enrollment['student_index'] ?? null;
    $_SESSION['auth_token'] = $auth_token;

    // Return a success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'student_id' => $student['student_id'],
        'student_auth_token' => $auth_token,
    ]);
} catch (Exception $e) {
    // Handle errors gracefully
    echo json_encode(['success' => false, 'message' => 'Error during login: ' . $e->getMessage()]);
    exit();
}
?>
