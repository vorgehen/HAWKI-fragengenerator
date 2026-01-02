<?php

session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit;
}

header('Content-Type: text/event-stream');
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
    error_log('document_id is required.');
    exit;
}

// Validate messages
if (empty($messages) || !is_array($messages)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'messages array is required.'
    ]);
    error_log('messages array is required.');
    exit;
}


// Python service URL
$lastElement = end($messages);

$content = $lastElement['content'];
$pythonServiceUrl = 'http://hawki.vorgehen.de:5000/document/'  . $documentId . '/Fragen' ;
error_log($pythonServiceUrl);;


$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $pythonServiceUrl);
curl_setopt($ch, CURLOPT_HTTPGET, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    error_log('cURL Error: ' . curl_error($ch));
    echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => 'Error: ' . curl_error($ch)]]]]) . "\n\n";
} else {
    // Parse the Python service response
    $pythonData = json_decode($response, true);
    error_log('Python service response: ' . $response);

    if ($pythonData && isset($pythonData['answer'])) {
        // Wrap in OpenAI streaming format
        echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => $pythonData['answer']]]]]) . "\n\n";
    } elseif ($pythonData && isset($pythonData['questions'])) {
        // Handle questions array format
        $questionsText = is_array($pythonData['questions']) ? implode("\n", $pythonData['questions']) : $pythonData['questions'];
        echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => $questionsText]]]]) . "\n\n";
    } elseif ($pythonData && isset($pythonData['content'])) {
        echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => $pythonData['content']]]]]) . "\n\n";
    } else {
        // Fallback: send raw response as content
        echo 'data: ' . json_encode(['choices' => [['delta' => ['content' => $response]]]]) . "\n\n";
    }
    echo "data: [DONE]\n\n";
}

if (ob_get_level() > 0) {
    ob_flush();
}
flush();
curl_close($ch);
