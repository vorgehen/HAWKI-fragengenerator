//#region UPLOAD FILE
const uploadQueues = new Map();

// Trigger click on the file input element
function selectFile() {
    document.querySelector('#file-upload-input').click();
}


function initFileUploader(inputField) {

    const overlay = inputField.querySelector('.drag-drop-overlay');
    const fileInput = document.querySelector('#file-upload-input');
    const input = inputField.querySelector('.input');
    let dragCounter = 0;

    // Drag and drop handling
    input.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    input.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter++;
        overlay.style.display = 'flex';
    });

    input.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter--;
        // Only hide if all drags have left this element
        if (dragCounter === 0) {
            overlay.style.display = 'none';
        }
    });

    input.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter = 0;
        overlay.style.display = 'none';

        handleSelectedFiles(e.dataTransfer.files, input);
    });

    // File input button handling
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            handleSelectedFiles(this.files, input);
        });
    }

}


// Handle files from drag-drop or file picker
async function handleSelectedFiles(files, inputField) {
    const fieldId = inputField.id;
    const attachmentContainer = inputField.querySelector('.file-attachments');

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
    Array.from(files).map(async file => {
        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showFeedbackMsg(inputField, 'error', `File type ${file.type} not supported.`);
            return null; // Early exit from this file's processing
        }

        // File size validation
        if (file.size > maxFileSize) {
            showFeedbackMsg(inputField, 'error', `File size exceeds ${maxMB}MB limit.`);
            return null;
        }

        // Prepare file for upload
        const fileData = createFileStruct(file);
        const atchThumb = createAttachmentThumbnail(fileData);

        // Add to file preview container
        if(!attachmentContainer.classList.contains('active')){
            attachmentContainer.classList.add('active');
        }

        //create a file queue
        if (!uploadQueues.has(fieldId)) {
            uploadQueues.set(fieldId, []);
        }
        attachmentContainer.querySelector('.attachments-list').appendChild(atchThumb);

        uploadQueues.get(fieldId).push({ fileData });
        setAttachmentsFilter(fieldId);

        // Return something useful if needed (optional)
        return;
    });
}

function setAttachmentsFilter(fieldId){

    const attachments = uploadQueues.get(fieldId);
    attachments.forEach(attachment => {
        const type = checkFileFormat(attachment.fileData.mime);
        if(type === 'pdf' || type === 'docx' || type === 'image'){
            addToModelFilters('file_upload', fieldId);
        }
        if(type === 'img'){
            addToModelFilters('vision', fieldId);
        }
    });
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

    const attachTemp = document.getElementById('attachment-thumbnail-template')
    const attachClone = attachTemp.content.cloneNode(true);
    const attachment = attachClone.querySelector(".attachment");
    attachment.dataset.fileId = fileData.tempId;
    attachment.querySelector('.name-tag').innerText = fileData.name;

    const iconImg = attachment.querySelector('img');
    let imgPreview = '';

    const type = checkFileFormat(fileData.mime);
    switch(type){
        case('img'):
        if(fileData.url){
            imgPreview = fileData.url;
        }
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
    const fileId = providerBtn.parentElement.dataset.fileId;
    // Remove from UI
    const fileElement = input.querySelector(`.attachment[data-file-id="${fileId}"]`);
    if (fileElement) {
        fileElement.remove();
    }

    // Remove from pending uploads array
    const queue = uploadQueues.get(input.id);

    if (queue) {
        const index = queue.findIndex(item => item.fileData.tempId === fileId);
        if (index !== -1) {
            queue.splice(index, 1);
        }
    }
    // If no more attachments, remove container
    const list = providerBtn.closest('.attachments-list');
    if (list && list.children.length === 0) {
        list.closest('.file-attachments').classList.remove('active');
    }
}


// Update file status in UI
function updateFileStatus(fileId, status) {
    const fileElement = document.querySelector(`.attachment[data-file-id="${fileId}"]`);

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
                showFeedbackMsg('')
                break;
        }
    }
}



/**
 * Upload all attachments from the queue to the server.
 *
 * @param {string} queueId - The ID of the upload queue.
 * @param {string} category - The category/type of upload.
 * @returns {Promise<array|null>} - List of uploaded file metadata or null.
 */
async function uploadAttachmentQueue(queueId, category) {
    const url = `/req/${category}/upload`;
    const attachments = uploadQueues.get(queueId);

    if (!attachments || attachments.length === 0) return null;

    const uploadedFiles = [];

    const uploadTasks = attachments.map(attachment => {
        updateFileStatus(attachment.fileData.tempId, 'uploading');

        const upload = uploadFileToServer(attachment.fileData, url, (tempId, status, percent, fileUrl = null) => {
            updateFileStatus(attachment.fileData.tempId, status, fileUrl);
        });

        const removeBtn = document.querySelector(`.attachment[data-file-id="${attachment.fileData.tempId}"]`).querySelector('.remove-btn');
        removeBtn.addEventListener('click', () => {
            upload.abort();
        });

        return upload.promise
            .then(data => {
                console.log(data)
                attachment.fileData.uuid = data.uuid;
                uploadedFiles.push({
                    uuid: data.uuid,
                    name: attachment.fileData.name,
                    mime: attachment.fileData.mime,
                });
                updateFileStatus(attachment.fileData.tempId, 'complete');
            })
            .catch(error => {
                console.error(`Upload failed for ${attachment.fileData.name}:`, error);
                updateFileStatus(attachment.fileData.tempId, 'error');
                // Optionally handle failed uploads
            });
    });

    await Promise.all(uploadTasks);
    return uploadedFiles;
}
