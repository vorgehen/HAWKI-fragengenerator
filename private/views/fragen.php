<?php
	
	$translation = $_SESSION['translation'];
?>

<form action="../app/php/fragen_send.php" method="post" enctype="multipart/form-data">
    <label for="pdfFile">Choose a PDF file:</label>
    <input type="file" name="pdfFile" id="pdfFile" accept=".pdf" required>
    <button type="submit">Upload</button>
</form>
