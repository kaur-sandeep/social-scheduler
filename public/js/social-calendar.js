document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('schedulerCalendar');
    if (!el) return;

    const modal = new bootstrap.Modal(document.getElementById('postModal'));
    const title = document.getElementById('postModalTitle');
    const body = document.getElementById('postModalBody');
    const platformFilter = document.getElementById('platformFilter');
    const statusFilter = document.getElementById('statusFilter');

    const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        height: 'auto',
        editable: true,
        nowIndicator: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events(info, success, failure) {
            const params = new URLSearchParams({
                start: info.startStr,
                end: info.endStr,
                platform: platformFilter.value,
                status: statusFilter.value
            });

            fetch(`${el.dataset.eventsUrl}?${params}`)
                .then(response => response.json())
                .then(success)
                .catch(failure);
        },
        eventClick(info) {
            const props = info.event.extendedProps;
            title.textContent = info.event.title;
            body.innerHTML = `
                ${props.thumbnail ? `<img class="img-fluid rounded mb-3" src="${props.thumbnail}" alt="">` : ''}
                <dl class="row mb-0">
                    <dt class="col-4">Platform</dt><dd class="col-8">${props.platform}</dd>
                    <dt class="col-4">Page</dt><dd class="col-8">${props.page || 'Profile'}</dd>
                    <dt class="col-4">Status</dt><dd class="col-8">${props.status}</dd>
                    <dt class="col-4">Caption</dt><dd class="col-8">${props.caption}</dd>
                </dl>
            `;
            modal.show();
        },
        eventDrop(info) {
            movePost(info.event);
        },
        eventResize(info) {
            movePost(info.event);
        }
    });

    function movePost(event) {
        fetch(`/posts/${event.id}/move`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                scheduled_at: event.start.toISOString(),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
            })
        }).then(response => {
            if (!response.ok) calendar.refetchEvents();
        });
    }

    platformFilter.addEventListener('change', () => calendar.refetchEvents());
    statusFilter.addEventListener('change', () => calendar.refetchEvents());
    calendar.render();
});
