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


$requestPayload = file_get_contents('php://input');
// Decode the JSON payload into an associative array
$decodedPayload = json_decode($requestPayload, true);



// Python service URL
$messagesEncoded = base64_encode(json_encode($requestPayload));
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
