<?php
// CORS headers
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Perform logout
session_unset();
session_destroy();
setcookie("admin_token", "", time() - 3600, "/", "", false, true);  

echo json_encode(["status" => "success", "message" => "Logout successful"]);
?>
