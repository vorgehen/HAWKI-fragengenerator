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
    // Initialize the Fragen upload form when this view is loaded
    if (typeof initializeFragenUpload === 'function') {
        initializeFragenUpload();
    } else {
        console.error('initializeFragenUpload function not found. Make sure interface.php is loaded.');
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