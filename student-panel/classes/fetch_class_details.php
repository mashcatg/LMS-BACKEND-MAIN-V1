<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$student_id = $_SESSION['student_id'] ?? '1';
$playlist_id = $_GET['playlist_id']; // Playlist ID from the URL
$class_id = $_GET['class_id']; // Class ID from the URL
$course_id = $_SESSION['course_id'] ?? '1'; // Get course_id from the session

// Validate that both playlist_id and class_id are provided
if (empty($playlist_id) || empty($class_id)) {
    echo json_encode(['error' => 'Missing playlist_id or class_id']);
    exit();
}

try {
    // Fetch the course_ids of the playlist from playlists table
    $stmt = $conn->prepare("
        SELECT course_id
        FROM playlists
        WHERE playlist_id = :playlist_id
    ");
    $stmt->execute([':playlist_id' => $playlist_id]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$playlist) {
        echo json_encode(['error' => 'Playlist not found']);
        exit();
    }

    $playlist_course_ids = $playlist['course_id'];

    // Check if the student is enrolled in any of the courses in the playlist
    $stmt_enrollments = $conn->prepare("
        SELECT course_id
        FROM enrollments
        WHERE student_id = :student_id
    ");
    $stmt_enrollments->execute([':student_id' => $student_id]);
    $enrolled_courses = $stmt_enrollments->fetchAll(PDO::FETCH_ASSOC);

    // Check if any of the enrolled courses match the playlist's courses
    $is_enrolled = false;
    foreach ($enrolled_courses as $enrollment) {
        if (strpos($playlist_course_ids, $enrollment['course_id']) !== false) {
            $is_enrolled = true;
            break;
        }
    }

    if (!$is_enrolled) {
        echo json_encode(['error' => 'Student is not enrolled in any of the courses in this playlist']);
        exit();
    }

    // Fetch the class details by class_id for the given playlist_id
    $stmt_class = $conn->prepare("
        SELECT class_id, class_name, class_link, note_id
         FROM classes
        WHERE playlist_id = :playlist_id AND class_id = :class_id ORDER BY class_index
    ");
    $stmt_class->execute([
        ':playlist_id' => $playlist_id,
        ':class_id' => $class_id
    ]);
    $class = $stmt_class->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        echo json_encode(['error' => 'Class not found']);
        exit();
    }

    // Check if note_id is null or empty
    $note_ids = !empty($class['note_id']) ? explode(",", $class['note_id']) : [];
    $notes = [];

    // Fetch notes if note_ids are present
    foreach ($note_ids as $note_id) {
        $stmt_notes = $conn->prepare("
            SELECT note_name, file_address, note_tags
            FROM notes
            WHERE note_id = :note_id
            AND FIND_IN_SET(:course_id, notes.course_id) > 0
        ");
        $stmt_notes->execute([
            ':note_id' => $note_id,
            ':course_id' => $course_id
        ]);
        $notes[] = $stmt_notes->fetch(PDO::FETCH_ASSOC);
    }

    // Add notes to the class
    $class['notes'] = $notes;

    // Return the class and note data as a JSON response
    echo json_encode(['class' => $class]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Error fetching class details: ' . $e->getMessage()]);
}
?>
