function initFileUploader(dropElement, fileInput) {

    window.uploadQueue = [];

    
    const overlay = dropElement.querySelector('.drag-drop-overlay');
    let dragCounter = 0;

    // Drag and drop handling
    dropElement.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    dropElement.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter++;
        overlay.style.display = 'flex';
    });

    dropElement.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter--;
        // Only hide if all drags have left this element
        if (dragCounter === 0) {
            overlay.style.display = 'none';
        }
    });

    dropElement.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        dragCounter = 0;
        overlay.style.display = 'none';

        handleSelectedFiles(e.dataTransfer.files);
    });

    // File input button handling
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            handleSelectedFiles(this.files);
        });
    }
}

// Initialize file uploader for the main interface
function initMainFileUploader() {
    // Initialize the window.uploadQueue array
    
    // Get elements
    const dropElement = document.getElementById('input-container');
    const fileInput = document.getElementById('file-upload-input');
    
    // Initialize uploader
    if (dropElement && fileInput) {
        initFileUploader(dropElement, fileInput);
    }
}

// Trigger click on the file input element
function selectFile() {
    document.getElementById('file-upload-input').click();
}

// Handle files from drag-drop or file picker
function handleSelectedFiles(files) {

    if (!files || files.length === 0) return;
    if(window.uploadQueue.length > 10){
        showError('Number of attached files exeeded');
    }

    const allowedTypes = [
        // Images
        'image/jpeg','image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf', 
        // 'application/msword', 
        // 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // 'application/vnd.ms-excel',
        // 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // 'application/vnd.ms-powerpoint',
        // 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // // Text
        // 'text/plain', 'text/csv', 'application/json',
        // 'text/html', 'text/css', 'text/javascript',
    ];
    
    const maxMB = 10;
    const maxFileSize = maxMB * 1024 * 1024; // 10MB limit
    
    // Process each file
    Array.from(files).forEach(file => {
        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showError(`File type ${file.type} not supported.`);
            return;
        }
        
        // File size validation
        if (file.size > maxFileSize) {
            showError(`File size exceeds ${maxMB}MB limit.`);
            return;
        }
        
        // Prepare file for upload
        const fileData = prepareFileForUpload(file);
        
        // Add file to UI
        addFileToUI(fileData);

        window.uploadQueue.push(fileData);
        // uploadFileToServer(fileData);
    });
}

// Show error message when file validation fails
function showError(message) {
    // You can implement this based on your UI design
    console.error(message);
    // Example: display toast notification
    if (window.showToast) {
        window.showToast(message, 'error');
    }
}

// Prepare file for upload by creating needed metadata
function prepareFileForUpload(file) {
    return {
        id: generateUniqueId(),
        file: file,
        name: file.name,
        size: file.size,
        type: file.type,
        lastModified: file.lastModified,
        status: 'pending' // pending, uploading, complete, error
    };
}

// Generate a unique ID for the file
function generateUniqueId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
}

// Add file to the UI for display
async function addFileToUI(fileData) {

    const prevTemp = document.getElementById('file-preview-thumb-template')
    const prevClone = prevTemp.content.cloneNode(true);
    const filePreview = prevClone.querySelector(".file-preview");
    filePreview.dataset.fileId = fileData.id;
    filePreview.querySelector('.file-type').innerText = getFileIconText(fileData.type)

    let imgPreview = '';
    // Different preview based on file type
    if (fileData.type.startsWith('image/')) {
        // Image preview
        imgPreview =  URL.createObjectURL(fileData.file);
        // imgPreview = createImagePreview(fileData)
    } 
    if(fileData.type.startsWith('application/pdf')){
        //pdf
        imgPreview = await createPdfPreview(fileData.file)
    }

    filePreview.querySelector('img').setAttribute('src', imgPreview);
    
    // Add to file preview container
    const previewContainer = document.querySelector('.file-attachments');
    previewContainer.appendChild(filePreview);
    if(!previewContainer.classList.contains('active')){
        previewContainer.classList.add('active')
    }
}


// Remove file attachment from UI and storage
function removeFileAttachment(providerBtn) {
    const fileId = providerBtn.parentElement.dataset.fileId;
    // Remove from UI
    const fileElement = document.querySelector(`.file-preview[data-file-id="${fileId}"]`);
    if (fileElement) {
        fileElement.remove();
    }
    
    // Remove from pending uploads array
    if (window.uploadQueue) {
        window.uploadQueue = window.uploadQueue.filter(item => item.id !== fileId);
    }
    
    // If no more attachments, remove container
    const container = document.querySelector('.file-attachments');
    if (container && container.children.length === 0) {
        container.classList.remove('active');
    }
}


/**
 * Renders the first page of a PDF file as a PNG data URL.
 * @param {File} pdfFile - A File object representing a user-uploaded PDF.
 * @param {Number} [thumbnailWidth=200] - Desired thumbnail width in pixels.
 * @returns {Promise<string>} - Resolves to a PNG data URL.
 */
async function createPdfPreview(pdfFile, thumbnailWidth = 200) {
    // Read file to ArrayBuffer
    const arrayBuffer = await pdfFile.arrayBuffer();

    // Load PDF document
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    const page = await pdf.getPage(1);

    // Compute viewport for the desired width
    const initialViewport = page.getViewport({ scale: 1 });
    const scale = thumbnailWidth / initialViewport.width;
    const viewport = page.getViewport({ scale });

    // Create canvas
    const canvas = document.createElement("canvas");
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    const ctx = canvas.getContext('2d');

    // Render page
    await page.render({ canvasContext: ctx, viewport }).promise;

    // Get PNG dataURL
    return canvas.toDataURL("image/png");
}

// Format file size for display
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}



// Get file icon text based on file type
function getFileIconText(fileType) {
    if (fileType.includes('pdf')) return 'PDF';
    if (fileType.includes('word')) return 'DOC';
    if (fileType.includes('excel') || fileType.includes('spreadsheet')) return 'XLS';
    if (fileType.includes('powerpoint') || fileType.includes('presentation')) return 'PPT';
    if (fileType.includes('zip') || fileType.includes('compressed')) return 'ZIP';
    if (fileType.includes('text')) return 'TXT';
    if (fileType.includes('image')) return 'IMG';
    return 'FILE';
}


// Upload file to server
async function uploadFileToServer(fileData) {
    // Create FormData
    const formData = new FormData();
    formData.append('file', fileData.file);
    formData.append('name', fileData.name);
    formData.append('type', fileData.type);
    
    // Update status to uploading
    updateFileStatus(fileData.id, 'uploading');
    
    // Send request to server
    await fetch('/req/upload-file', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {

        console.log(data);
        // // Update with server file info
        // updateFileStatus(fileData.id, 'complete', data.fileUrl);
        // return data;
    })
    .catch(error => {
        console.error('Upload error:', error);
        updateFileStatus(fileData.id, 'error');
        showError('Failed to upload file. Please try again.');
    });
}


function uploadFileToServer(fileData) {
    // Create FormData
    const formData = new FormData();
    formData.append('file', fileData.file);
    formData.append('name', fileData.name);
    formData.append('type', fileData.type);
    
    // Update status to uploading
    updateFileStatus(fileData.id, 'uploading');
    
    // Send request to server
    fetch('/req/upload-file', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {

        console.log(data);
        // Update with server file info
        updateFileStatus(fileData.id, 'complete', data.fileUrl);
        return data;
    })
    .catch(error => {
        console.error('Upload error:', error);
        updateFileStatus(fileData.id, 'error');
        showError('Failed to upload file. Please try again.');
    });
}

// Update file status in UI
function updateFileStatus(fileId, status, fileUrl = null) {
    const fileElement = document.querySelector(`.file-preview[data-file-id="${fileId}"]`);
    if (!fileElement) return;
    
    // Remove existing status classes
    fileElement.classList.remove('status-pending', 'status-uploading', 'status-complete', 'status-error');
    fileElement.classList.add(`status-${status}`);
    
    // Update any status indicators in the UI
    const statusIndicator = fileElement.querySelector('.status-indicator');
    const stats = statusIndicator.querySelectorAll('.status');
    stats.forEach(stat => {
        stat.style.visibility = "hidden";
    });

    if (statusIndicator) {
        switch (status) {
            case 'uploading':
                statusIndicator.querySelector('#upload-stat').style.visibility = 'visible'
                break;
            case 'complete':
                statusIndicator.querySelector('#complete-stat').style.visibility = 'visible'
                break;
            case 'error':
                statusIndicator.querySelector('#error-stat').style.visibility = 'visible'
                showError('')
                break;
        }
    }
    
    // If we have the uploaded file URL, store it
    if (fileUrl && fileElement.dataset) {
        fileElement.dataset.fileUrl = fileUrl;
    }
    
    // Update in pending uploads array
    if (window.uploadQueue) {
        window.uploadQueue.forEach(item => {
            if (item.id === fileId) {
                item.status = status;
                if (fileUrl) item.fileUrl = fileUrl;
            }
        });
    }
}

// Get all attached files in a format ready to send with message
function getAttachedFiles() {
    const fileElements = document.querySelectorAll('.file-preview');
    const files = [];
    
    fileElements.forEach(element => {
        files.push({
            id: element.dataset.fileId,
            url: element.dataset.fileUrl || null,
            name: element.querySelector('.file-info').textContent.split(' (')[0],
            status: element.className.includes('status-complete') ? 'complete' : 'pending'
        });
    });
    
    return files;
}

// Check if there are any pending file uploads
function getPendingUploads() {
    let pendingUploads = [];

    window.uploadQueue.forEach(file => {
        if(file.status === 'pending'){
            pendingUploads.push(file);
        }
    });
    return pendingUploads;
}

// Upload all pending files
async function uploadAllPendingFiles() {
    const pendingFiles = getPendingUploads();
    




    pendingFiles.forEach(element => {
        const fileId = element.dataset.fileId;
        const fileData = window.uploadQueue.find(item => item.id === fileId);
        if (fileData) {
            uploadFileToServer(fileData);
        }
    });
    
    return pendingFiles.length > 0;
}