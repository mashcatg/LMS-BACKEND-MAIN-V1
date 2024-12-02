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

$service_id = $_SESSION['service_id'] ;
$course_id = $_SESSION['course_id'];

if (empty($course_id)) {
    echo json_encode(['error' => 'No Course Selected']);
    exit();
}
try {
    // Prepare the SQL query to fetch playlists with aggregated data
    $stmt = $conn->prepare("
        SELECT 
            playlists.playlist_id, 
            playlists.playlist_name, 
            playlists.description, 
            playlists.course_id, 
            playlists.service_id, 
            GROUP_CONCAT(courses.course_name SEPARATOR ', ') AS course_names
        FROM playlists
        INNER JOIN courses ON FIND_IN_SET(courses.course_id, playlists.course_id) > 0
        WHERE playlists.service_id = :service_id
        AND FIND_IN_SET(:course_id, playlists.course_id) > 0
        GROUP BY playlists.playlist_id, playlists.playlist_name, playlists.description, playlists.course_id, playlists.service_id
    ");
    
    // Execute the query with the course_id and service_id from the session
    $stmt->execute([
        ':course_id' => $course_id, 
        ':service_id' => $_SESSION['service_id']
    ]);
    
    $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Now, for each playlist, count the number of classes (videos) associated with the playlist_id
    foreach ($playlists as &$playlist) {
        // Prepare the query to count the number of classes (videos) in the playlist
        $stmtCount = $conn->prepare("
            SELECT COUNT(*) as numberOfVideos
            FROM classes 
            WHERE classes.playlist_id = :playlist_id
        ");
        
        // Execute the query to count the number of classes (videos) for the current playlist
        $stmtCount->execute([':playlist_id' => $playlist['playlist_id']]);
        
        // Fetch the result of the count and add it to the playlist array
        $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
        $playlist['numberOfVideos'] = $countResult['numberOfVideos'];
    }

    // Return playlists with the course_name and numberOfVideos as a JSON response
    echo json_encode(['playlistData' => $playlists]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching playlists: ' . $e->getMessage()]);
}
