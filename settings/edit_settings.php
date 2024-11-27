<?php

// PHP Script to handle file uploads and settings update
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

try {
    // Default paths for favicon and logo
    $saveFaviconPath = null;
    $saveLogoPath = null;

    // Fetch current settings to get existing favicon and logo
    $stmt = $conn->prepare("SELECT favicon, logo FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $currentImages = $stmt->fetch(PDO::FETCH_ASSOC);

    // Handle favicon upload
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $faviconTmpPath = $_FILES['favicon']['tmp_name'];
        $faviconFilename = uniqid('favicon_', true) . '.' . pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
        $faviconPath = '../uploads/' . $faviconFilename;
        $saveFaviconPath = 'http://lms.ennovat.com/lms-admin/uploads/' . $faviconFilename;
        move_uploaded_file($faviconTmpPath, $faviconPath);
    } else {
        $saveFaviconPath = $currentImages['favicon'] ?? null; // Retain existing favicon or keep null
    }

    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoTmpPath = $_FILES['logo']['tmp_name'];
        $logoFilename = uniqid('logo_', true) . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $logoPath = '../uploads/' . $logoFilename;
        $saveLogoPath = 'http://lms.ennovat.com/lms-admin/uploads/' . $logoFilename;
        move_uploaded_file($logoTmpPath, $logoPath);
    } else {
        $saveLogoPath = $currentImages['logo'] ?? null; // Retain existing logo or keep null
    }

    // Access the rest of the form data via $_POST
    $siteName = $_POST['site_name'] ?? null;
    $phoneNumber = $_POST['phone_number'] ?? null;
    $location = $_POST['location'] ?? null;
    $colors = json_decode($_POST['colors'], true);
    $socialLinks = json_decode($_POST['social_links'], true);

    // Prepare the SQL query for updating relevant fields
    $stmt = $conn->prepare("UPDATE services SET 
        company_name = ?, 
        ad_phone = ?, 
        address = ?, 
        favicon = ?, 
        logo = ?, 
        primary_color = ?, 
        background_color = ?, 
        text_color = ?, 
        accent_color = ?, 
        facebook = ?, 
        instagram = ?, 
        twitter = ?, 
        linkedin = ?, 
        youtube = ? 
        WHERE service_id = ?");

    // Execute the query with the updated values
    $stmt->execute([
        $siteName,
        $phoneNumber,
        $location,
        $saveFaviconPath,
        $saveLogoPath,
        $colors['primary'] ?? null,
        $colors['background'] ?? null,
        $colors['text'] ?? null,
        $colors['accent'] ?? null,
        $socialLinks['facebook'] ?? null,
        $socialLinks['instagram'] ?? null,
        $socialLinks['twitter'] ?? null,
        $socialLinks['linkedin'] ?? null,
        $socialLinks['youtube'] ?? null,
        $service_id
    ]);

    echo json_encode(['success' => true, 'message' => 'Settings updated successfully!']);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating settings.']);
}
