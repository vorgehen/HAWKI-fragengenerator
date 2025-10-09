<?php
	
	$translation = $_SESSION['translation'];
?>

<form action="api/fragen_send" method="post" enctype="multipart/form-data">
    <label for="pdfFile">Choose a PDF file:</label>
    <input type="file" name="pdfFile" id="pdfFile" accept=".pdf" required>
     <button type="submit">Upload</button>
</form>
<h1>PDF Datei hochladen und vektorisieren</h1>
<!-- File upload form -->
<form id="uploadForm">
    <input type="file" id="fileInput" name="file" required>
    <button type="submit">Upload</button>
</form>

<p id="status"></p>

<script>
    // Select form and elements
    const form = document.getElementById('uploadForm');
    const fileInput = document.getElementById('fileInput');
    const status = document.getElementById('status');

    // Add event listener for form submission
    form.addEventListener('submit',  (event) => {
        event.preventDefault(); // Prevent default form submission
        const feedback_send = "api/fragen_send"
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Check if a file is selected
        if (!fileInput.files.length) {
            status.textContent = "Please select a file.";
            return;
        }

        const file = fileInput.files[0]; // Get the selected file
        const formData = new FormData(); // Create a FormData object
        formData.append('file', file); // Append the file to the FormData object



        try {
            // Send the file to the server using fetch
            const response =  fetch('api/fragen_send', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken, // Include CSRF token in the request headers
                },
            })
            ;

            if (response.ok) {
                status.textContent = "Datei erfolgreich hochgeladen!";
            } else {
                status.textContent = `Hochladen fehlgeschlagen: ${response.statusText}`;
            }
        } catch (error) {
            status.textContent = `Error: ${error.message}`;
        }
    });
</script>