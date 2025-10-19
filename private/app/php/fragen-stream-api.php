<?php

session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Add this block to handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$_SESSION['last_activity'] = time();


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
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    echo $data;
    // ob_flush();
    flush();
    return strlen($data);
});

curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}

curl_close($ch);
