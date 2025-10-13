<?php
$translation = $_SESSION['translation'];
?>

<div class="message me" data-role="system">
    <div class="message-content">
        <div class="message-icon"><?php echo $translation["System"]; ?></div>
        <div class="message-text">
            <?php echo $translation["Fragen_System_Content"] ?? "Upload a PDF file to generate questions and get answers based on the document content."; ?>
        </div>
    </div>
</div>

<div class="fragen-upload-section">
    <h3><?php echo $translation["Upload_PDF"] ?? "Upload PDF Document"; ?></h3>
    <form id="fragen-upload-form" enctype="multipart/form-data">
        <div class="file-input-wrapper">
            <label for="pdfFile" class="file-label">
                <svg viewBox="0 0 24 24" width="24" height="24">
                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                </svg>
                <?php echo $translation["Choose_PDF"] ?? "Choose a PDF file"; ?>
            </label>
            <input type="file" name="pdfFile" id="pdfFile" accept=".pdf" required style="display: none;">
            <span id="file-name" class="file-name-display"></span>
        </div>
        <button type="submit" id="upload-btn" disabled>
            <?php echo $translation["Upload"] ?? "Upload"; ?>
        </button>
        <div id="upload-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <span class="progress-text">Uploading...</span>
        </div>
    </form>

    <div id="upload-status"></div>

    <div id="document-info" style="display: none;">
        <h4><?php echo $translation["Current_Document"] ?? "Current Document"; ?></h4>
        <p id="doc-name"></p>
        <p id="doc-id" style="font-size: 0.8em; color: #666;"></p>
        <button onclick="clearDocument()"><?php echo $translation["Clear_Document"] ?? "Clear Document"; ?></button>
    </div>
</div>

<script>
    // Store the document ID in sessionStorage for later use
    let currentDocumentId = sessionStorage.getItem('fragen_document_id');
    let currentDocumentName = sessionStorage.getItem('fragen_document_name');


    // Initialize UI on load
    window.addEventListener('DOMContentLoaded', () => {
        if (currentDocumentId) {
            displayDocumentInfo(currentDocumentName, currentDocumentId);
        }

        // File input change handler
        const fileInput = document.getElementById('pdfFile');
        const uploadBtn = document.getElementById('upload-btn');
        const fileNameDisplay = document.getElementById('file-name');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileNameDisplay.textContent = file.name;
                uploadBtn.disabled = false;
            } else {
                fileNameDisplay.textContent = '';
                uploadBtn.disabled = true;
            }
        });
    });

    // Handle form submission
    document.getElementById('fragen-upload-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData();
        const fileInput = document.getElementById('pdfFile');
        const file = fileInput.files[0];

        if (!file) {
            showStatus('Please select a file', 'error');
            return;
        }

        formData.append('pdfFile', file);

        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Show progress
        document.getElementById('upload-progress').style.display = 'block';
        document.getElementById('upload-btn').disabled = true;

        try {
            const response = await fetch('api/fragen_send', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            });

            const data = await response.json();

            // Update CSRF token
            if (data.csrf_token) {
                document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
            }

            if (data.success && data.document_id) {
                // Store the document ID and name in sessionStorage
                sessionStorage.setItem('fragen_document_id', data.document_id);
                sessionStorage.setItem('fragen_document_name', file.name);

                currentDocumentId = data.document_id;
                currentDocumentName = file.name;

                showStatus(data.message || 'File uploaded successfully!', 'success');
                displayDocumentInfo(file.name, data.document_id);

                // Reset form
                fileInput.value = '';
                document.getElementById('file-name').textContent = '';
            } else {
                showStatus(data.message || 'Upload failed', 'error');
            }
        } catch (error) {
            console.error('Upload error:', error);
            showStatus('An error occurred during upload', 'error');
        } finally {
            document.getElementById('upload-progress').style.display = 'none';
            document.getElementById('upload-btn').disabled = false;
        }
    });

    function displayDocumentInfo(name, id) {
        document.getElementById('doc-name').textContent = name;
        document.getElementById('doc-id').textContent = `ID: ${id}`;
        document.getElementById('document-info').style.display = 'block';
    }

    function clearDocument() {
        sessionStorage.removeItem('fragen_document_id');
        sessionStorage.removeItem('fragen_document_name');
        currentDocumentId = null;
        currentDocumentName = null;
        document.getElementById('document-info').style.display = 'none';
        showStatus('Document cleared', 'info');
    }

    function showStatus(message, type) {
        const statusDiv = document.getElementById('upload-status');
        statusDiv.textContent = message;
        statusDiv.className = `status-message status-${type}`;
        statusDiv.style.display = 'block';

        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }

    // Function to get current document ID for use in queries
    function getCurrentDocumentId() {
        return sessionStorage.getItem('fragen_document_id');
    }
</script>

<style>
    .fragen-upload-section {
        padding: 20px;
        max-width: 600px;
        margin: 0 auto;
    }

    .file-input-wrapper {
        margin: 20px 0;
    }

    .file-label {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 20px;
        background: var(--primary-color, #2336b0);
        color: white;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s;
    }

    .file-label:hover {
        background: var(--primary-hover, #1a2880);
    }

    .file-name-display {
        margin-left: 10px;
        font-style: italic;
        color: #666;
    }

    #upload-btn {
        padding: 10px 30px;
        background: var(--accent-color, #06B044);
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        transition: background 0.3s;
    }

    #upload-btn:hover:not(:disabled) {
        background: var(--accent-hover, #059038);
    }

    #upload-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    #upload-progress {
        margin-top: 15px;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 5px;
    }

    .progress-fill {
        height: 100%;
        background: var(--accent-color, #06B044);
        animation: progress 2s ease-in-out infinite;
    }

    @keyframes progress {
        0% { width: 0%; }
        50% { width: 70%; }
        100% { width: 100%; }
    }

    .progress-text {
        font-size: 14px;
        color: #666;
    }

    .status-message {
        padding: 10px;
        margin: 15px 0;
        border-radius: 5px;
        font-weight: 500;
    }

    .status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .status-info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    #document-info {
        margin-top: 30px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }

    #document-info h4 {
        margin-top: 0;
        color: #333;
    }

    #document-info button {
        margin-top: 10px;
        padding: 8px 16px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.3s;
    }

    #document-info button:hover {
        background: #c82333;
    }
</style>