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
    // const maxMB = 10;
    // const maxFileSize = maxMB * 1024 * 1024; // 10MB limit

    // Convert FileList to Array and process all files in parallel
    const tasks = Array.from(files).map(async file => {

        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showError(`File type ${file.type} not supported.`);
            return null; // Early exit from this file's processing
        }

        // File size validation
        // if (file.size > maxFileSize) {
        //     showError(`File size exceeds ${maxMB}MB limit.`);
        //     return null;
        // }

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
        mime: file.type,
        lastModified: file.lastModified,
        status: 'pending' // pending, uploading, complete, error
    };
}



// Add file to the UI for display
function createAttachmentThumbnail(fileData) {

    const attachTemp = document.getElementById('attachment-template')
    const attachClone = attachTemp.content.cloneNode(true);
    const attachment = attachClone.querySelector(".attachment");
    attachment.dataset.fileId = fileData.uuid;
    attachment.querySelector('.name-tag').innerText = fileData.name;

    const iconImg = attachment.querySelector('img');
    let imgPreview = '';
    // console.log(fileData)

    const type = checkFileFormat(fileData.mime);
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

    iconImg.setAttribute('src', imgPreview);
    return attachment;
}

// Remove file attachment from UI and storage
function removeFileAttachment(providerBtn) {
    const input = providerBtn.closest('.input');
    const list = providerBtn.closest('.attachment-list');
    // console.log('remove');
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


// Generate a unique ID for the file
function generateUniqueId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
}






//#endregion

//#region UPLOAD DOWNLOAD

async function uploadAttachmentQueue(queueId, category) {
    const url = `/req/${category}/upload`;
    const attachments = uploadQueues.get(queueId);
    let attachList = [];
    if (attachments && attachments.length > 0) {
        const uploadTasks = Array.from(attachments).map(attachment =>
            uploadFileToServer(attachment.fileData, url, (tempId, status, percent, fileUrl) => {
                // UI/status updates centralized here!
                // if (onProgress) {
                    // console.log(percent);
                    // onProgress(tempId, status, percent, fileUrl, attachment);
                // }
            })
            .then(data => {
                attachment.fileData.uuid = data.uuid;
                attachList.push({
                    uuid: data.uuid,
                    name: attachment.fileData.name,
                    mime: attachment.fileData.mime
                });
            })
            .catch(err => {
                // Optionally: log, and attach error status
                // if (onProgress) {
                    // onProgress(attachment.fileData.tempId, 'error', 100, null, attachment);
                // }
            })
        );
        await Promise.all(uploadTasks);
        return attachList;
    }
    return null;
}


// Upload file to server with progress callback
function uploadFileToServer(fileData, url, progressCallback) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('file', fileData.file);

        if (progressCallback) progressCallback(fileData.tempId, 'uploading', 0);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable && progressCallback) {
                const percent = Math.round((event.loaded / event.total) * 100);
                progressCallback(fileData.tempId, 'uploading', percent);
            }
        };

        xhr.onload = function() {
            let data;
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    data = JSON.parse(xhr.responseText);
                } catch (e) {
                    if (progressCallback) progressCallback(fileData.tempId, 'error', 100);
                    return reject('Invalid server response');
                }
                if (progressCallback) progressCallback(data.requestId || fileData.tempId, 'complete', 100, data.fileUrl);
                resolve(data);
            } else {
                if (progressCallback) progressCallback(fileData.tempId, 'error', 100);
                reject(xhr.statusText);
            }
        };

        xhr.onerror = function() {
            if (progressCallback) progressCallback(fileData.tempId, 'error', 100);
            reject('Network error');
        };

        xhr.send(formData);
    });
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

        // console.log(data);
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

//#endregion


//#region PREVIEW

async function previewFile(fileData, category) {
    const url = await requestFileUrl(fileData.uuid, category, fileData.filename)
    const response = await fetch(url);
    const blob = await response.blob();

    const type = checkFileFormat(fileData.mime);

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
//#endregion
