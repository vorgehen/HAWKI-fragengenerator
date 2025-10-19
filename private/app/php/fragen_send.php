<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once  LIBRARY_PATH . 'csrf.php';

// Check if the user is logged in, if not return 401 Unauthorized
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$uploadDir = "/var/www/FraGen-Service/resources/";

// Ensure the upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create directory with proper permissions
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Protection
    if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        $csrf_token = generate_csrf_token();
        echo json_encode([
            'success' => false,
            'csrf_token' => $csrf_token,
            'message' => 'CSRF token validation failed.'
        ]);
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
        $csrf_token = generate_csrf_token();
        echo json_encode([
            'success' => false,
            'csrf_token' => $csrf_token,
            'message' => 'File upload error.'
        ]);
        exit;
    }

    $file = $_FILES['pdfFile'];
    
    // Validate file type
    $allowedMimeTypes = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimeTypes)) {
        $csrf_token = generate_csrf_token();
        echo json_encode([
            'success' => false,
            'csrf_token' => $csrf_token,
            'message' => 'Only PDF files are allowed.'
        ]);
        exit;
    }

    // Validate file size (e.g., max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        $csrf_token = generate_csrf_token();
        echo json_encode([
            'success' => false,
            'csrf_token' => $csrf_token,
            'message' => 'File size exceeds maximum limit of 10MB.'
        ]);
        exit;
    }

    // Generate unique document ID
    $documentId = uniqid('doc_', true);
    
    // Save file with unique name
    $fileName = $documentId . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $csrf_token = generate_csrf_token();
        echo json_encode([
            'success' => false,
            'csrf_token' => $csrf_token,
            'message' => 'Failed to save file.'
        ]);
        exit;
    }

    // Send file to Python service for indexing
    $pythonServiceUrl = 'http://hawki.vorgehen.de:5000/document/" . $fileName';
    $ch = curl_init($pythonServiceUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new CURLFile($filePath), 'document_id' => $documentId]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // For now, just return success with document ID
    $csrf_token = generate_csrf_token();
    echo json_encode([
        'success' => true,
        'csrf_token' => $csrf_token,
        'document_id' => $documentId,
        'file_name' => $file['name'],
        'message' => 'File uploaded successfully and ready for processing.'
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit;
?>
