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
$playlist_id = $_GET['id']; // Playlist ID from the URL
$course_id = $_SESSION['course_id']; // Get course_id from the session

// Get the course_ids of the playlist from playlists table
try {
    // Fetch playlist course_ids
    $stmt = $conn->prepare("
        SELECT course_id
        FROM playlists
        WHERE playlist_id = :playlist_id
    ");
    $stmt->execute([':playlist_id' => $playlist_id]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($playlist) {
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

        if ($is_enrolled) {
            // Fetch the classes for the playlist_id
            $stmt_classes = $conn->prepare("
                SELECT class_id, class_name, class_link, note_id
                FROM classes
                WHERE playlist_id = :playlist_id
            ");
            $stmt_classes->execute([':playlist_id' => $playlist_id]);
            $classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

            foreach ($classes as &$class) {
    // Check if note_id is null or an empty string
    $note_ids = !empty($class['note_id']) ? explode(",", $class['note_id']) : [];

    $notes = [];
    
    // If there are note_ids, fetch the notes
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

    // Add the notes to the class
    $class['notes'] = $notes;
}


            // Return the class and note data as JSON response
            echo json_encode(['classes' => $classes]);
        } else {
            echo json_encode(['error' => 'Student is not enrolled in any of the courses in this playlist']);
        }
    } else {
        echo json_encode(['error' => 'Playlist not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error fetching class data: ' . $e->getMessage()]);
}
?>
