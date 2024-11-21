<?php
// Connect to the database
$host = 'localhost';
$dbname = 'ennovatc_lms';
$username = 'ennovatc_lms'; 
$password = 'g3QK5We=aSi_';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
// include 'check_auth.php';
?>