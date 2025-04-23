function initFileUploader(dropElement, fileInput) {

    window.pendingUploads = [];


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
    // Initialize the window.pendingUploads array
    
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
    
    const allowedTypes = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Text
        'text/plain', 'text/csv', 'application/json',
        'text/html', 'text/css', 'text/javascript',
        // Archives (if supported)
        'application/zip', 'application/x-rar-compressed'
    ];
    
    const maxFileSize = 10 * 1024 * 1024; // 10MB limit
    
    // Process each file
    Array.from(files).forEach(file => {
        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showFileError(`File type ${file.type} not supported.`);
            return;
        }
        
        // File size validation
        if (file.size > maxFileSize) {
            showFileError(`File size exceeds 10MB limit.`);
            return;
        }
        
        // Prepare file for upload
        const fileData = prepareFileForUpload(file);
        
        // Add file to UI
        addFileToUI(fileData);

        window.pendingUploads.push(fileData);
        console.log(window.pendingUploads);
    });
}

// Show error message when file validation fails
function showFileError(message) {
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
function addFileToUI(fileData) {

    const prevTemp = document.getElementById('file-preview-thumb-template')
    const prevClone = prevTemp.content.cloneNode(true);
    const filePreview = prevClone.querySelector(".file-preview");
    filePreview.dataset.fileId = fileData.id;

    // Different preview based on file type
    if (fileData.type.startsWith('image/')) {
        // Image preview
        const imgPreview =  URL.createObjectURL(fileData.file);
        filePreview.querySelector('img').setAttribute('src', imgPreview);
    } else {
        // Document/other file preview
        const docPreview = createDocumentPreview(fileData);
        filePreview.appendChild(docPreview);
    }

    
    // Add to file preview container
    const previewContainer = document.querySelector('.file-attachments');
    previewContainer.appendChild(filePreview);
    if(!previewContainer.classList.contains('active')){
        previewContainer.classList.add('active')
    }
}

// Format file size for display
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
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
    if (window.pendingUploads) {
        window.pendingUploads = window.pendingUploads.filter(item => item.id !== fileId);
    }
    
    // If no more attachments, remove container
    const container = document.querySelector('.file-attachments');
    if (container && container.children.length === 0) {
        container.classList.remove('active');
    }
}


// Create preview for image files
// function createImagePreview(fileData) {
//     const preview = document.createElement('div');
//     preview.className = 'image-preview';
    
//     // Create thumbnail
//     const img = document.createElement('img');
//     img.src = URL.createObjectURL(fileData.file);
//     img.src = URL.createObjectURL(fileData.file);
//     img.onload = function() {
//         URL.revokeObjectURL(this.src);
//     };
//     preview.appendChild(img);
    
//     // Add file info
//     const info = document.createElement('div');
//     info.className = 'file-info';
//     info.textContent = `${fileData.name} (${formatFileSize(fileData.size)})`;
//     preview.appendChild(info);
    
//     return preview;
// }

// Create preview for document files
// function createDocumentPreview(fileData) {
//     const preview = document.createElement('div');
//     preview.className = 'document-preview';
    
//     // Add file icon based on type
//     const icon = document.createElement('div');
//     icon.className = 'file-icon';
//     icon.textContent = getFileIconText(fileData.type);
//     preview.appendChild(icon);
    
//     // Add file info
//     const info = document.createElement('div');
//     info.className = 'file-info';
//     info.textContent = `${fileData.name} (${formatFileSize(fileData.size)})`;
//     preview.appendChild(info);
    
//     return preview;
// }

// Get file icon text based on file type
// function getFileIconText(fileType) {
//     if (fileType.includes('pdf')) return 'PDF';
//     if (fileType.includes('word')) return 'DOC';
//     if (fileType.includes('excel') || fileType.includes('spreadsheet')) return 'XLS';
//     if (fileType.includes('powerpoint') || fileType.includes('presentation')) return 'PPT';
//     if (fileType.includes('zip') || fileType.includes('compressed')) return 'ZIP';
//     if (fileType.includes('text')) return 'TXT';
//     return 'FILE';
// }


// // Upload file to server
// function uploadFileToServer(fileData) {
//     // Create FormData
//     const formData = new FormData();
//     formData.append('file', fileData.file);
//     formData.append('name', fileData.name);
//     formData.append('type', fileData.type);
    
//     // Update status to uploading
//     updateFileStatus(fileData.id, 'uploading');
    
//     // Send request to server
//     fetch('/api/upload', {
//         method: 'POST',
//         headers: {
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
//         },
//         body: formData
//     })
//     .then(response => {
//         if (!response.ok) {
//             throw new Error('Network response was not ok');
//         }
//         return response.json();
//     })
//     .then(data => {
//         // Update with server file info
//         updateFileStatus(fileData.id, 'complete', data.fileUrl);
//         return data;
//     })
//     .catch(error => {
//         console.error('Upload error:', error);
//         updateFileStatus(fileData.id, 'error');
//         showFileError('Failed to upload file. Please try again.');
//     });
// }

// Update file status in UI
// function updateFileStatus(fileId, status, fileUrl = null) {
//     const fileElement = document.querySelector(`.file-preview[data-file-id="${fileId}"]`);
//     if (!fileElement) return;
    
//     // Remove existing status classes
//     fileElement.classList.remove('status-pending', 'status-uploading', 'status-complete', 'status-error');
//     fileElement.classList.add(`status-${status}`);
    
//     // Update any status indicators in the UI
//     const statusIndicator = fileElement.querySelector('.file-status');
//     if (statusIndicator) {
//         switch (status) {
//             case 'pending':
//                 statusIndicator.textContent = 'Ready to upload';
//                 break;
//             case 'uploading':
//                 statusIndicator.textContent = 'Uploading...';
//                 break;
//             case 'complete':
//                 statusIndicator.textContent = 'Uploaded';
//                 break;
//             case 'error':
//                 statusIndicator.textContent = 'Error';
//                 break;
//         }
//     }
    
//     // If we have the uploaded file URL, store it
//     if (fileUrl && fileElement.dataset) {
//         fileElement.dataset.fileUrl = fileUrl;
//     }
    
//     // Update in pending uploads array
//     if (window.pendingUploads) {
//         window.pendingUploads.forEach(item => {
//             if (item.id === fileId) {
//                 item.status = status;
//                 if (fileUrl) item.fileUrl = fileUrl;
//             }
//         });
//     }
// }

// Get all attached files in a format ready to send with message
// function getAttachedFiles() {
//     const fileElements = document.querySelectorAll('.file-preview');
//     const files = [];
    
//     fileElements.forEach(element => {
//         files.push({
//             id: element.dataset.fileId,
//             url: element.dataset.fileUrl || null,
//             name: element.querySelector('.file-info').textContent.split(' (')[0],
//             status: element.className.includes('status-complete') ? 'complete' : 'pending'
//         });
//     });
    
//     return files;
// }

// Check if there are any pending file uploads
// function hasPendingUploads() {
//     const pendingFiles = document.querySelectorAll('.file-preview:not(.status-complete):not(.status-error)');
//     return pendingFiles.length > 0;
// }

// Upload all pending files
// function uploadAllPendingFiles() {
//     const pendingFiles = document.querySelectorAll('.file-preview.status-pending');
    
//     pendingFiles.forEach(element => {
//         const fileId = element.dataset.fileId;
//         const fileData = window.pendingUploads.find(item => item.id === fileId);
//         if (fileData) {
//             uploadFileToServer(fileData);
//         }
//     });
    
//     return pendingFiles.length > 0;
// }