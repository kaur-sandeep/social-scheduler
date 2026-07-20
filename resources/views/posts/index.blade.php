@extends('layouts.app')

@section('title', 'Scheduled Posts')
@section('subtitle', 'Drafts, queued posts, publishing history, and failures')

@section('content')
<div class="panel">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
        <form method="get" action="{{ route('posts.index') }}" class="post-search" id="post-search-form">
            <i class="bi bi-search"></i>
            <input type="search" name="q" id="post-search" value="{{ request('q') }}" placeholder="Search by ID, caption, platform, page or project" autocomplete="off">
            @if(request('q'))<a href="{{ route('posts.index') }}" class="btn btn-sm btn-link text-decoration-none">Clear</a>@endif
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('posts.deleted') }}">View deleted posts</a>
    </div>
    <div id="post-results">@include('posts.partials.results')</div>
</div>
@endsection

@push('scripts')
<script>
const searchForm = document.getElementById('post-search-form');
const searchInput = document.getElementById('post-search');
const results = document.getElementById('post-results');
let searchTimer;

async function loadPosts(url) {
    results.classList.add('post-results-loading');
    try {
        const response = await fetch(url, {headers: {Accept: 'application/json'}});
        if (!response.ok) return;
        const data = await response.json();
        results.innerHTML = data.html;
        history.replaceState({}, '', url);
    } finally { results.classList.remove('post-results-loading'); }
}

searchForm?.addEventListener('submit', event => { event.preventDefault(); loadPosts(`${searchForm.action}?${new URLSearchParams(new FormData(searchForm))}`); });
searchInput?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => searchForm.requestSubmit(), 350); });
results?.addEventListener('click', event => { const link = event.target.closest('.pagination a'); if (!link) return; event.preventDefault(); loadPosts(link.href); });
</script>
@endpush
