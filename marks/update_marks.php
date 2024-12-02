<?php 
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL); 

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

include '../check_auth_backend.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true); // Decode JSON input
    $service_id = $_SESSION['service_id'];
    // Validate and sanitize input
    $exam_id = isset($data['exam_id']) ? intval($data['exam_id']) : 0;
    $mark_id = isset($_GET['mark_id']) ? intval($_GET['mark_id']) : 0;
    $student_index = isset($data['student_index']) ? htmlspecialchars(trim($data['student_index'])) : '';
    $mcq_marks = isset($data['mcq_marks']) ? floatval($data['mcq_marks']) : 0.0;
    $cq_marks = isset($data['cq_marks']) ? floatval($data['cq_marks']) : 0.0;
    $practical_marks = isset($data['practical_marks']) ? floatval($data['practical_marks']) : 0.0;
    
    // Validate required fields
    if (empty($student_index) || $exam_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }

    try {
        // Check if the mark already exists for the student in this exam
        $stmt = $conn->prepare("SELECT marks_id FROM marks WHERE exam_id = ? AND student_index = ?");
        $stmt->execute([$exam_id, $student_index]);
        $existingMark = $stmt->fetch();

        if ($existingMark) {
            // Update existing marks
            $stmt = $conn->prepare("UPDATE marks SET mcq_marks = ?, cq_marks = ?, practical_marks = ? WHERE marks_id = ?");
            $stmt->execute([
                $mcq_marks,
                $cq_marks,
                $practical_marks,
                $existingMark['marks_id'] // Use the existing marks_id for the update
            ]);
        } else {
            // Insert new marks
            $stmt = $conn->prepare("INSERT INTO marks (exam_id, student_index, mcq_marks, cq_marks, practical_marks, created_at, service_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([
                $exam_id,
                $student_index,
                $mcq_marks,
                $cq_marks,
                $practical_marks,
                $service_id
            ]);

        }

        echo json_encode(['success' => true, 'message' => 'Marks updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
