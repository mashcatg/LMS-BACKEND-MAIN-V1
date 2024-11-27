<?php
// Connect to the database
$host = 'localhost';
$dbname = '';
$username = 'lms'; 
$password = 'mashPass789!@#';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
// include 'check_auth.php';
?>
