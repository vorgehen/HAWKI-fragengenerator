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

$uploadDir = "/var/www/FraGen-Service/resources/";

// Ensure the upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create directory with proper permissions
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Protection TODO

    $resultOK = false;
    $newFileName ="";
    $message = "";
    $new_id = 0;

    if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
        $fileName = $_FILES['pdfFile']['name'];
        $fileSize = $_FILES['pdfFile']['size'];
        $fileType = $_FILES['pdfFile']['type'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allowed file type
        $allowedExtensions = ['pdf'];



        if (in_array($fileExtension, $allowedExtensions)) {
            // Validate file size (e.g., max 20MB)
            if ($fileSize <= 20 * 1024 * 1024) {
                // Generate a unique name for the file to avoid overwriting
                $newFileName = uniqid('pdf_', true) . '.' . $fileExtension;

                // Move the file to the upload directory
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $message = "Datei erfolgreich hochgeladen: " . htmlspecialchars($newFileName);
                    $resultOK = true;
                } else {
                    $message =  "Fehler beim Verschieben der Datei.";
                }
            } else {
                $message =  "Datei ist groesser als das 20MB limit.";
            }
        } else {
            $message =  "Nur PDF Dateien sind erlaubt.";
        }
    } else {
        $message =  "No file uploaded or an error occurred.";
    }

    if (  $resultOK) {
        // Initialize a cURL session
                $curl = curl_init();

        // Set the URL for the API request
                $url = "http://127.0.0.1:5000/document/" . $newFileName ;


        // Set cURL options for POST request
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request and get the response
                $curl_response = curl_exec($curl);

        // Close the cURL session
                curl_close($curl);

        // Decode the JSON response
                $data = json_decode($curl_response, true);

        // Print the response data
                print_r($data);
                $new_id = $data['document_id'];
                $message =$message . " ... und vektorisiert";
    }

    $csrf_token = generate_csrf_token();
    $response = array(
        'success' => $resultOK,
        'message' => $message,
        'csrf_token' => $csrf_token,
        'id' => $new_id,
        'data'=> $data
    );


    // Return the JSON string
    echo json_encode($response);
}
?>
