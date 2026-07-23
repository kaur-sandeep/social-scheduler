@extends('layouts.app')

@section('title', 'Scheduled Posts')
@section('subtitle', 'Drafts, queued posts, publishing history, and failures')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
@endpush

@section('content')
<div class="panel">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-3">
        <form method="get" action="{{ route('posts.index') }}" id="post-search-form" class="post-filter-form w-100">
            <div class="post-filter-bar">
                <div class="post-search">
            <i class="bi bi-search"></i>
            <input type="search" name="q" id="post-search" value="{{ request('q') }}" placeholder="Search by ID, caption, platform, page or project" autocomplete="off">
                </div>
                <select name="platform[]" class="form-select form-select-sm filter-multiselect" multiple aria-label="Filter by platform" data-placeholder="All platforms">
                    @foreach(['facebook','instagram','linkedin','twitter','tiktok','youtube','pinterest','threads'] as $provider)<option value="{{ $provider }}" @selected(in_array($provider, (array) request('platform', [])))>{{ ucfirst($provider) }}</option>@endforeach
                </select>
                <select name="project[]" class="form-select form-select-sm filter-multiselect" multiple aria-label="Filter by project" data-placeholder="All projects">
                    @foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array((string) $project->id, array_map('strval', (array) request('project', []))))>{{ $project->name }}</option>@endforeach
                </select>
                <select name="status[]" class="form-select form-select-sm filter-multiselect" multiple aria-label="Filter by status" data-placeholder="All statuses">
                    @foreach(['draft','pending','queued','publishing','published','failed','cancelled'] as $status)<option value="{{ $status }}" @selected(in_array($status, (array) request('status', [])))>{{ ucfirst($status) }}</option>@endforeach
                </select>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" aria-label="Scheduled from">
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}" aria-label="Scheduled to">
                <input type="hidden" name="sort" value="{{ request('sort', 'id') }}"><input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
                <button class="btn btn-sm btn-outline-primary" type="submit">Apply</button>
                @if(request()->hasAny(['q','platform','project','status','date_from','date_to']))<a href="{{ route('posts.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear</a>@endif
            </div>
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('posts.deleted') }}">View deleted posts</a>
    </div>
    <div id="post-results">@include('posts.partials.results')</div>
</div>

<div class="modal fade" id="postDetailsModal" tabindex="-1" aria-labelledby="postDetailsModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="postDetailsModalTitle">Post details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0 post-details-list" id="post-details-content"></dl></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
const searchForm = document.getElementById('post-search-form');
const searchInput = document.getElementById('post-search');
const results = document.getElementById('post-results');
let searchTimer;

function formatLocalDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat(undefined, {dateStyle: 'medium', timeStyle: 'short'}).format(date);
}

function localizeDates(scope = document) {
    scope.querySelectorAll('[data-local-date]').forEach(element => {
        const formatted = formatLocalDate(element.dataset.localDate);
        element.textContent = formatted;
        element.title = formatted;
    });
}

document.querySelectorAll('.filter-multiselect').forEach(select => window.Choices && new Choices(select, {
    allowHTML: false,
    itemSelectText: '',
    removeItemButton: true,
    searchEnabled: true,
    searchPlaceholderValue: 'Search…',
    shouldSort: false,
    placeholder: true,
    placeholderValue: select.dataset.placeholder,
}));

async function loadPosts(url) {
    results.classList.add('post-results-loading');
    try {
        const response = await fetch(url, {headers: {Accept: 'application/json'}});
        if (!response.ok) return;
        const data = await response.json();
        results.innerHTML = data.html;
        localizeDates(results);
        history.replaceState({}, '', url);
    } finally { results.classList.remove('post-results-loading'); }
}

searchForm?.addEventListener('submit', event => { event.preventDefault(); loadPosts(`${searchForm.action}?${new URLSearchParams(new FormData(searchForm))}`); });
searchInput?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => searchForm.requestSubmit(), 350); });
searchForm?.querySelectorAll('select,input[type="date"]').forEach(input => input.addEventListener('change', () => searchForm.requestSubmit()));
results?.addEventListener('click', event => {
    const viewButton = event.target.closest('[data-view-post]');
    if (viewButton) {
        let media = [];
        try { media = JSON.parse(viewButton.dataset.media || '[]'); } catch (error) { media = []; }
        const fields = [
            ['Post ID', `#${viewButton.dataset.postId}`], ['Project', viewButton.dataset.project], ['Platform', viewButton.dataset.platform],
            ['Destination', viewButton.dataset.destination], ['Status', viewButton.dataset.status], ['Scheduled at', formatLocalDate(viewButton.dataset.scheduledAt)],
            ['Published at', formatLocalDate(viewButton.dataset.publishedAt)], ['Caption', viewButton.dataset.caption || '—']
        ];
        if (viewButton.dataset.status === 'failed') fields.push(['Failure reason', viewButton.dataset.errorMessage || 'No failure reason was recorded.']);
        const details = document.getElementById('post-details-content');
        details.replaceChildren();
        fields.forEach(([label, value]) => {
            const term = document.createElement('dt'); term.className = 'col-sm-4'; term.textContent = label;
            const description = document.createElement('dd'); description.className = 'col-sm-8'; description.textContent = value;
            details.append(term, description);
        });
        const mediaTerm = document.createElement('dt'); mediaTerm.className = 'col-sm-4'; mediaTerm.textContent = 'Media';
        const mediaDescription = document.createElement('dd'); mediaDescription.className = 'col-sm-8 post-detail-media';
        if (!media.length) {
            mediaDescription.textContent = 'No media';
        } else {
            media.forEach(item => {
                const mediaElement = document.createElement(item.type === 'video' ? 'video' : 'img');
                mediaElement.src = item.url;
                mediaElement.className = 'post-detail-media-item';
                if (item.type === 'video') { mediaElement.controls = true; mediaElement.preload = 'metadata'; mediaElement.playsInline = true; }
                else mediaElement.alt = 'Post media';
                mediaDescription.append(mediaElement);
            });
        }
        details.append(mediaTerm, mediaDescription);
        new bootstrap.Modal(document.getElementById('postDetailsModal')).show();
        return;
    }
    const link = event.target.closest('.pagination a'); if (!link) return; event.preventDefault(); loadPosts(link.href);
});
localizeDates();
</script>
@endpush
