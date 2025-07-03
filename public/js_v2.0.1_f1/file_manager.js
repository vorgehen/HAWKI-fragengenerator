//#region UPLOAD FILE
const uploadQueues = new Map();
function initFileUploader(inputField) {
    

    const overlay = inputField.querySelector('.drag-drop-overlay');
    const fileInput = inputField.querySelector('#file-upload-input');
    let dragCounter = 0;

    // Drag and drop handling
    inputField.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    inputField.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter++;
        overlay.style.display = 'flex';
    });

    inputField.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter--;
        // Only hide if all drags have left this element
        if (dragCounter === 0) {
            overlay.style.display = 'none';
        }
    });

    inputField.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        dragCounter = 0;
        overlay.style.display = 'none';

        handleSelectedFiles(e.dataTransfer.files, inputField);
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
async function handleSelectedFiles(files, inputField) {
    const fieldId = inputField.id;
    if (!files || files.length === 0) return;

    const allowedTypes = [
        // Images
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf',
        'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        // 'application/vnd.ms-excel',
        // 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // 'application/vnd.ms-powerpoint',
        // 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // // Text
        // 'text/plain', 'text/csv', 'application/json',
    ];
    const maxMB = 10;
    const maxFileSize = maxMB * 1024 * 1024; // 10MB limit

    // Convert FileList to Array and process all files in parallel
    const tasks = Array.from(files).map(async file => {

        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showError(`File type ${file.type} not supported.`);
            return null; // Early exit from this file's processing
        }

        // File size validation
        if (file.size > maxFileSize) {
            showError(`File size exceeds ${maxMB}MB limit.`);
            return null;
        }

        // Prepare file for upload
        const fileData = createFileStruct(file);
        const attachment = createAttachmentThumbnail(fileData);
        // Add to file preview container
        const attachmentContainer = inputField.querySelector('.file-attachments');
        if(!attachmentContainer.classList.contains('active')){
            attachmentContainer.classList.add('active');
        }
        if (!uploadQueues.has(fieldId)) {
            uploadQueues.set(fieldId, []);
        }
        uploadQueues.get(fieldId).push({ fileData });
        attachmentContainer.querySelector('.attachments-list').appendChild(attachment);
        // Return something useful if needed (optional)
        return { fileData, attachment };
        // uploadFileToServer(fileData); // Enable if you want to upload after
    });

    // Wait for all tasks to complete
    await Promise.all(tasks);
}



// Show error message when file validation fails
function showError(message) {
    // You can implement this based on your UI design
    console.error(message);
    // Example: display toast notification
    // if (window.showToast) {
    //     window.showToast(message, 'error');
    // }
}

// Prepare file for upload by creating needed metadata
function createFileStruct(file) {
    return {
        tempId: generateUniqueId(),
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
function createAttachmentThumbnail(fileData) {

    const attachTemp = document.getElementById('attachment-template')
    const attachClone = attachTemp.content.cloneNode(true);
    const attachment = attachClone.querySelector(".attachment");
    attachment.dataset.fileId = fileData.uuid;
    attachment.querySelector('.name-tag').innerText = fileData.name;

    const imgElement = attachment.querySelector('img');
    let imgPreview = '';

    const type = checkFileFormat(fileData.type);
    switch(type){
        case('img'):
        if (fileData.file) {
            imgPreview = URL.createObjectURL(fileData.file);
        }
        attachment.querySelector('.attachment-icon').classList.add('boarder');
        break;
        case('pdf'): 
            imgPreview = '/img/fileformat/pdf.png';
        break;
        case('docx'):
            imgPreview = '/img/fileformat/doc.png';
        break;
    }

    imgElement.setAttribute('src', imgPreview);
    return attachment;
}

// Remove file attachment from UI and storage
function removeFileAttachment(providerBtn) {
    const input = providerBtn.closest('.input');
    const list = providerBtn.closest('.attachment-list');
    console.log('remove');
    const fileId = providerBtn.parentElement.dataset.fileId;
    // Remove from UI
    const fileElement = input.querySelector(`.attachment[data-file-id="${fileId}"]`);
    if (fileElement) {
        fileElement.remove();
    }
    
    // Remove from pending uploads array
    if (window.uploadQueue) {
        window.uploadQueue = window.uploadQueue.filter(item => item.id !== fileId);
    }
    
    // If no more attachments, remove container
    if (list && list.children.length === 0) {
        container.classList.remove('active');
    }
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
async function uploadFileToServer(fileData, category) {
    // Create FormData
    const formData = new FormData();
    formData.append('file', fileData.file);
    formData.append('category', category);

    // Update status to uploading
    updateFileStatus(fileData.tempId, 'uploading');
    
    try {
        // Send request to server
        const response = await fetch('/req/upload-file', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        const data = await response.json();
        console.log('uploaded file data');
        console.log(data)
        updateFileStatus(data.requestId, 'complete', data.fileUrl);
        return data;
    } catch (error) {
        console.error('Upload error:', error);
        updateFileStatus(fileData.tempId, 'error');
        showError(`Failed to upload file ${fileData.name}. Please try again.`);
        throw error; // Re-throw to allow Promise.allSettled to catch it
    }
}

// Update file status in UI
function updateFileStatus(fileId, status, fileUrl = null) {
    const fileElement = document.querySelector(`.attachment-icon[data-file-id="${fileId}"]`);
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

    // Update in pending uploads array
    if (window.uploadQueue) {
        window.uploadQueue.forEach(item => {
            if (item.id === fileId) {
                item.status = status;
            }
        });
    }
}
//#endregion


//#region Utils

function checkFileFormat(type){

    if (type.startsWith('image/')) {
        return 'img';
    } else if (type.includes('pdf')) {
        return 'pdf';
    } else if (type.includes('msword') || 
               type.includes('wordprocessingml')) {
        return 'docx';
    } else {
        return null;
    }

}


//#endregion





//#region DOWNLOAD FILE



// async function downloadAttachment(uuid, category){

//     const requestObj = json.stringify({
//         'category': category,
//         'uuid': uuid
//     });
    
//     try {
//         // Send request to server
//         const response = await fetch('/req/download-file', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
//             },
//             body: requestObj
//         });

//         if (!response.ok) {
//             throw new Error('Network response was not ok');
//         }
//         const data = await response.json();
//         console.log(data)
//         updateFileStatus(data.requestId, 'complete', data.fileUrl);
//         return data;
//     } catch (error) {
//         console.error('Upload error:', error);
//         updateFileStatus(fileData.tempId, 'error');
//         showError(`Failed to upload file ${fileData.name}. Please try again.`);
//         throw error; // Re-throw to allow Promise.allSettled to catch it
//     }



// }




async function previewFile(fileData, category) {
    const url = await requestFileUrl(fileData.uuid, category, fileData.filename)
    const response = await fetch(url);
    const blob = await response.blob();
    
    const type = checkFileFormat(fileData.type);

    switch(type){
        case('img'):
        if (fileData.file) {
            imgPreview = URL.createObjectURL(fileData.file);
        }
        attachment.querySelector('.attachment-icon').classList.add('boarder');
        break;
        case('pdf'): 
            renderPdf(blob);
        break;
        case('docx'):
            renderDocx(blob);

        break;
    }



    document.querySelector('#file-viewer-modal').style.display = "flex";


}



async function requestFileUrl(uuid, category, filename){
    try {
        const response = await fetch('/req/create-download-link', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            uuid: uuid,
            category: category,
            filename: filename // only needed if your backend needs it
        })
        });

        const data = await response.json();

        if (data.success && data.url) {
        // Automatically start download

        console.log(data);
        return data.url;
        const link = document.createElement('a');
        link.href = data.url;
        link.download = filename; // optional, sets the filename for the download
        } else {
        alert('Failed to get download link');
        }
    } catch (err) {
        console.error('Download error:', err);
        alert('An error occurred while requesting the file.');
    }
}




async function renderPdf(blob) {

    const arrayBuffer = await blob.arrayBuffer();

    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

    const container = document.getElementById('file-preview-container');
    container.innerHTML = ''; // Clear previous pages

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const pdfPage  = await pdf.getPage(pageNum);
        const viewport = pdfPage.getViewport({ scale: 1 });

        // ── Page wrapper ───────────────────────────────────────────────
        const pageDiv = document.createElement('div');
        pageDiv.className = 'pdf-page';
        pageDiv.style.position = 'relative';
        pageDiv.style.margin = '1rem auto';
        pageDiv.style.width = '100%';
        pageDiv.style.maxWidth = `${viewport.width}px`;

        // ── Responsive canvas ─────────────────────────────────────────
        const canvas   = document.createElement('canvas');
        const context  = canvas.getContext('2d');
        canvas.width   = viewport.width;
        canvas.height  = viewport.height;
        canvas.style.width  = '100%';
        canvas.style.height = 'auto';
        canvas.style.display = 'block';
        pageDiv.appendChild(canvas);

        await pdfPage.render({ canvasContext: context, viewport }).promise;

        // ── Text layer ────────────────────────────────────────────────
        // const textLayerBuilder = new TextLayerBuilder({
        //     pdfPage,
        //     textLayerMode: 2 // Use enhanced layout for better accuracy
        // });
        // console.log(textLayerBuilder)
        // await textLayerBuilder.render({ viewport });
        
        // const textLayerDiv = textLayerBuilder.div;
        // textLayerDiv.style.position = 'absolute';
        // textLayerDiv.style.top  = '0';
        // textLayerDiv.style.left = '0';
        // textLayerDiv.style.width  = '100%';
        // textLayerDiv.style.height = '100%';

        // pageDiv.appendChild(textLayerDiv);

        // ── Append to container ───────────────────────────────────────
        container.appendChild(pageDiv);
    }

}



async function renderDocx(blob){
    const container = document.getElementById('file-preview-container');
    container.innerHTML = '';

    docxPreview.renderAsync(blob, container)
        .then(x => console.log("docx: finished"));
}
