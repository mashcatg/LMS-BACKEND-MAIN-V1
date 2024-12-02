<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include 'db.php';
$service_id = $_SESSION['service_id'] ?? 61545;

function hexToHsl($hex) {
    // Remove the '#' if it exists
    $hex = ltrim($hex, '#');

    // Convert hex to RGB
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;

    // Find the max and min values of RGB
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);

    $h = 0;
    $s = 0;
    $l = ($max + $min) / 2;

    if ($max == $min) {
        $h = $s = 0; // No saturation or hue if max == min
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        switch ($max) {
            case $r:
                $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                break;
            case $g:
                $h = ($b - $r) / $d + 2;
                break;
            case $b:
                $h = ($r - $g) / $d + 4;
                break;
        }
        $h /= 6;
    }

    // Convert HSL to percentage
    $h = round($h * 360) . 'deg';  // Add 'deg' to the hue
    $s = round($s * 100) . '%';
    $l = round($l * 100) . '%';

    return ['h' => $h, 's' => $s, 'l' => $l];
}

try {
    // Prepare the SQL query to fetch colors related to the current service
    $stmt = $conn->prepare("SELECT accent_color, text_color, background_color, primary_color FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $colors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert each color from hex to HSL
    $colors = array_map(function($color) {
        return [
            'accent_color' => hexToHsl($color['accent_color']),
            'text_color' => hexToHsl($color['text_color']),
            'background_color' => hexToHsl($color['background_color']),
            'primary_color' => hexToHsl($color['primary_color'])
        ];
    }, $colors);

    // Return colors as a JSON response
    echo json_encode(['success' => true, 'colors' => $colors]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching colors: ' . $e->getMessage()]);
}
