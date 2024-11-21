<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow cross-origin requests from frontend on localhost:3000
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
session_set_cookie_params([
    'lifetime' => 180 * 24 * 60 * 60, // 180 days
    'path' => '/',                     // Available site-wide
    'domain' => '.youthsthought.com',   // Valid for subdomains of youthsthought.com
    'secure' => true,                  // Secure only over HTTPS
    'httponly' => true,                // Make the cookie inaccessible to JavaScript
    'samesite' => 'None',              // Allow cross-site cookie usage
]);

// Start the session after setting cookie parameters
session_start();
include 'db.php';

$postData = json_decode(file_get_contents('php://input'), true);
$currentUrl = "https://enno.ennovat.com/";

if (!$currentUrl) {
    echo json_encode(['error' => 'No URL provided']);
    exit;
}

// Validate URL format
$currentUrl = filter_var($currentUrl, FILTER_VALIDATE_URL);
if ($currentUrl === false) {
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Parse the URL and extract the subdomain
$parsedUrl = parse_url($currentUrl);
$host = $parsedUrl['host'] ?? null;
$subdomainParts = $host ? explode('.', $host) : [];
$subdomain = count($subdomainParts) > 2 ? $subdomainParts[0] : null;

if (!$subdomain) {
    echo json_encode(['error' => 'No subdomain found']);
    exit;
}

try {
    // Query the database for the service ID using the subdomain
    $stmt = $conn->prepare("SELECT service_id FROM services WHERE sub_domain = ?");
    $stmt->execute([$subdomain]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($service && isset($service['service_id'])) {
        // Save the service ID in the session
        $_SESSION['service_id'] = $service['service_id'];

        // Set the service_id cookie with necessary attributes
        setcookie('service_id', $service['service_id'], [
            'expires' => time() + (180 * 24 * 60 * 60),  // 180 days
            'path' => '/',                              // Available site-wide
            'domain' => '.youthsthought.com',            // Valid for subdomains of youthsthought.com
            'secure' => true,                           // Secure only over HTTPS
            'httponly' => true,                         // Make it inaccessible to JavaScript
            'samesite' => 'None'                        // Allow cross-site cookie usage
        ]);

        echo json_encode([
            'url' => $currentUrl,
            'subdomain' => $subdomain,
            'service_id' => $service['service_id'],
            'message' => 'Service ID set successfully',
            'sessions' => $_SESSION,
        ]);
    } else {
        echo json_encode([
            'url' => $currentUrl,
            'subdomain' => $subdomain,
            'error' => 'Service not found for the given subdomain',
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>
