<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once  LIBRARY_PATH . 'csrf.php';

// Check if the user is logged in, if not return 401 Unauthorized
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

$uploadDir = "/var/www/FraGen-Service/resources";

// Ensure the upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create directory with proper permissions
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Protection TODO


    if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
        $fileName = $_FILES['pdfFile']['name'];
        $fileSize = $_FILES['pdfFile']['size'];
        $fileType = $_FILES['pdfFile']['type'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allowed file type
        $allowedExtensions = ['pdf'];

        // Validate file type
        if (in_array($fileExtension, $allowedExtensions)) {
            // Validate file size (e.g., max 5MB)
            if ($fileSize <= 5 * 1024 * 1024) {
                // Generate a unique name for the file to avoid overwriting
                $newFileName = uniqid('pdf_', true) . '.' . $fileExtension;

                // Move the file to the upload directory
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    echo "File uploaded successfully: " . htmlspecialchars($newFileName);
                } else {
                    echo "Error moving the uploaded file.";
                }
            } else {
                echo "File size exceeds the 5MB limit.";
            }
        } else {
            echo "Only PDF files are allowed.";
        }
    } else {
        echo "No file uploaded or an error occurred.";
    }
}
?>
