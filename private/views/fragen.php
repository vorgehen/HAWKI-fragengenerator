<?php
	
	$translation = $_SESSION['translation'];
?>

<form action="api/fragen_send" method="post" enctype="multipart/form-data">
    <label for="pdfFile">Choose a PDF file:</label>
    <input type="file" name="pdfFile" id="pdfFile" accept=".pdf" required>
    <button type="submit">Upload</button>
</form>
