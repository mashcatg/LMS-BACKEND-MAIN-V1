<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'] ;
$course_id = $_SESSION['course_id'];

try {
    // Total Exams
    $stmt_exams = $conn->prepare("
        SELECT COUNT(*) as total_exams
        FROM exams 
        WHERE service_id = :service_id
        AND FIND_IN_SET(:course_id, course_id) > 0
        AND student_visibility = 1
    ");
    $stmt_exams->execute([':service_id' => $service_id, ':course_id' => $course_id]);
    $total_exams = $stmt_exams->fetch(PDO::FETCH_ASSOC)['total_exams'];

    // Total Quizzes
    $stmt_quizzes = $conn->prepare("
        SELECT COUNT(*) as total_quizzes
        FROM quizzes 
        WHERE service_id = :service_id
        AND FIND_IN_SET(:course_id, course_id) > 0
        AND student_visibility = 1
    ");
    $stmt_quizzes->execute([':service_id' => $service_id, ':course_id' => $course_id]);
    $total_quizzes = $stmt_quizzes->fetch(PDO::FETCH_ASSOC)['total_quizzes'];

    // Playlists and Classes
    $stmt_playlists = $conn->prepare("
        SELECT playlist_id
        FROM playlists
        WHERE service_id = :service_id
        AND FIND_IN_SET(:course_id, course_id) > 0
    ");
    $stmt_playlists->execute([':service_id' => $service_id, ':course_id' => $course_id]);
    $playlists = $stmt_playlists->fetchAll(PDO::FETCH_ASSOC);

    $total_classes = 0;
    foreach ($playlists as $playlist) {
        $stmt_classes = $conn->prepare("
            SELECT COUNT(*) as total_classes
            FROM classes
            WHERE playlist_id = :playlist_id
        ");
        $stmt_classes->execute([':playlist_id' => $playlist['playlist_id']]);
        $total_classes += $stmt_classes->fetch(PDO::FETCH_ASSOC)['total_classes'];
    }

    // Final Response
    echo json_encode([
        'success' => true,
        'totals' => [
            'exams' => $total_exams,
            'quizzes' => $total_quizzes,
            'classes' => $total_classes
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
