
let announcement_queue = [];
let currentAnnouncementIndex = 0;

function initAnnouncements(announcementList){
    // create a queue
    announcement_queue = announcementList || [];
    console.log(announcement_queue);
    if (announcement_queue.length > 0) {
        currentAnnouncementIndex = 0;
        renderNextAnnouncement();
    }
}

function renderNextAnnouncement(){
    if (currentAnnouncementIndex < announcement_queue.length) {
        renderAnnouncement(announcement_queue[currentAnnouncementIndex]);
    }
}

function renderAnnouncement(announcement){
    // Request announcement render from server
    fetch(`/req/announcement/render/${announcement.id}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if(data.success){
            console.log(data);
            // Create and show announcement modal
            showAnnouncementModal(announcement, data.view);
            // Mark as seen immediately when displayed
            markAnnouncementAsSeen(announcement.id);
        }
    })
    .catch(error => {
        console.error('Error loading announcement:', error);
        // Skip to next announcement on error
        moveToNextAnnouncement();
    });
}

function showAnnouncementModal(announcement, view) {
    // Insert the rendered content
    const modal = document.querySelector('#announcements-modal');
    const contentWrapper = modal.querySelector('.content-box');
    const html = md.render(view);
    contentWrapper.innerHTML = html;

    contentWrapper.querySelectorAll('a').forEach(a => {
        a.setAttribute('target', '_blank');
        a.setAttribute("rel", "noopener noreferrer");
    });


    const btnBar = document.createElement('div');
    btnBar.classList.add('modal-buttons-bar');


    const confirmBtn = document.createElement('button');
    confirmBtn.className = "btn-lg-fill align-end";
    confirmBtn.textContent = translation.Confirm;
    confirmBtn.addEventListener('click', function() {
        reportAnnouncementFeedback(announcement.id, true); // or whatever your parameters are
        closeAnnouncementModal(modal);
    });

    if(announcement.type === "force"){
        const cancelBtn = document.createElement('button');
        cancelBtn.className = "btn-lg-stroke align-end";
        cancelBtn.textContent = translation.Cancel;
        cancelBtn.addEventListener('click', () => {
            forceLogoutUser(modal);
        });
        btnBar.appendChild(cancelBtn);
    }
    btnBar.appendChild(confirmBtn);

    contentWrapper.appendChild(btnBar);

    modal.style.display = 'flex'

}

function closeAnnouncementModal(annModal) {
    annModal.style.display = 'none';
    annModal.querySelector('.content-box').innerHTML = "";
    moveToNextAnnouncement();
}


async function forceLogoutUser(announcementId){
    const confirmed = await openModal(ModalType.WARNING , "To contninue using HAWKI you need to accept this. click on confirm to logout from HAWKI.");
    if (!confirmed) {
        return;
    }
    logout();
}


function moveToNextAnnouncement() {
    currentAnnouncementIndex++;
    // Small delay before showing next announcement
    setTimeout(() => {
        renderNextAnnouncement();
    }, 300);
}

function markAnnouncementAsSeen(announcementId) {
    fetch(`/req/announcement/seen/${announcementId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .catch(error => console.error('Error marking announcement as seen:', error));
}

function reportAnnouncementFeedback(announcementId){

    fetch(`/req/announcement/report/${announcementId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`Announcement Confirmed`);
        }
    })
    .catch(error => console.error('Error reporting announcement feedback:', error));
}
