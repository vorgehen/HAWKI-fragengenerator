<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once LIBRARY_PATH . 'csrf.php';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Method not allowed.'
    ]);
    exit;
}

// Get the JSON request body
$jsonString = file_get_contents("php://input");
$requestData = json_decode($jsonString, true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Invalid JSON in request body.'
    ]);
    exit;
}

// Extract document_id and messages
$documentId = isset($requestData['document_id']) ? $requestData['document_id'] : null;
$messages = isset($requestData['messages']) ? $requestData['messages'] : [];

// Validate document_id
if (empty($documentId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'document_id is required.'
    ]);
    exit;
}

// Validate messages
if (empty($messages) || !is_array($messages)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'messages array is required.'
    ]);
    exit;
}


// Python service URL
$messagesEncoded = base64_encode(json_encode($messages));
$pythonServiceUrl = 'http://hawki.vorgehen.de:5000/document/'  . $documentId . '/' . $messagesEncoded;


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $pythonServiceUrl);
curl_setopt($ch, CURLOPT_HTTPGET, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);



// Execute the request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
} else {
    echo "Response: " . $response;
}

// Close cURL session
curl_close($ch);