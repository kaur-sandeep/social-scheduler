document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('schedulerCalendar');
    if (!el) return;

    const modal = new bootstrap.Modal(document.getElementById('postModal'));
    const title = document.getElementById('postModalTitle');
    const body = document.getElementById('postModalBody');
    const platformFilter = document.getElementById('platformFilter');
    const projectFilter = document.getElementById('projectFilter');
    const statusFilter = document.getElementById('statusFilter');

    const calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        eventDisplay: 'block',
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
                project: projectFilter.value,
                status: statusFilter.value
            });

            fetch(`${el.dataset.eventsUrl}?${params}`)
                .then(response => response.json())
                .then(success)
                .catch(failure);
        },
        eventClassNames(info) {
            return ['calendar-event', `calendar-status-${info.event.extendedProps.status || 'draft'}`];
        },
        eventClick(info) {
            const props = info.event.extendedProps;
            title.textContent = info.event.title;
            body.innerHTML = `
                ${props.thumbnail ? `<img class="img-fluid rounded mb-3" src="${props.thumbnail}" alt="">` : ''}
                <dl class="row mb-0">
                    <dt class="col-4">Platform</dt><dd class="col-8">${props.platform}</dd>
                    <dt class="col-4">Page</dt><dd class="col-8">${props.page || 'Profile'}</dd>
                    <dt class="col-4">Project</dt><dd class="col-8">${props.project || '-'}</dd>
                    <dt class="col-4">Status</dt><dd class="col-8">${props.status}</dd>
                    <dt class="col-4">Caption</dt><dd class="col-8">${props.caption}</dd>
                </dl>
            `;
            modal.show();
        },
        eventDidMount(info) {
            const props = info.event.extendedProps;
            const statusColors = {
                draft: ['#ffedd5', '#c2410c'],
                pending: ['#fee2e2', '#b42318'],
                queued: ['#fee2e2', '#b42318'],
                retrying: ['#fee2e2', '#b42318'],
                published: ['#dcfae6', '#087443'],
                failed: ['#fef3f2', '#b42318'],
                cancelled: ['#fef3f2', '#b42318'],
                publishing: ['#e0f2fe', '#0369a1'],
            };
            const [background, foreground] = statusColors[props.status] || ['#64748b', '#ffffff'];
            const eventParts = [info.el, ...info.el.querySelectorAll('.fc-event-main, .fc-event-main-frame, .fc-event-title-container, .fc-event-title, .fc-event-time')];
            eventParts.forEach(part => {
                part.style.setProperty('background-color', background, 'important');
                part.style.setProperty('color', foreground, 'important');
            });
            info.el.title = `${props.project || 'No project'} · ${props.status} · ${props.caption || ''}`;
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
    projectFilter.addEventListener('change', () => calendar.refetchEvents());
    statusFilter.addEventListener('change', () => calendar.refetchEvents());
    calendar.render();
});
