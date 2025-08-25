
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

    const modal = document.getElementById('announcements-modal');
    const contentWrapper = modal.querySelector('.modal-content');
    const html = md.render(view);
    contentWrapper.innerHTML = html;

    contentWrapper.querySelectorAll('a').forEach(a => {
        a.setAttribute('target', '_blank');
        a.setAttribute("rel", "noopener noreferrer");
    });

    modal.style.display = 'flex'

}

function closeAnnouncementModal(overlay) {
    document.body.removeChild(overlay);
    moveToNextAnnouncement();
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

function reportAnnouncementFeedback(announcementId, action){
    // Tell server if the user has accepted the announcement
    const endpoint = action === 'accepted' ? 'accepted' : 'dismissed';

    fetch(`/req/announcement/${endpoint}/${announcementId}`, {
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
            console.log(`Announcement ${action}:`, announcementId);
        }
    })
    .catch(error => console.error('Error reporting announcement feedback:', error));
}
