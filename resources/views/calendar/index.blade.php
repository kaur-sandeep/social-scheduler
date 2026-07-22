@extends('layouts.app')

@section('title', 'Calendar')
@section('subtitle', 'Multi-platform publishing calendar')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="panel calendar-panel">
    <div class="calendar-toolbar">
        <select id="projectFilter" class="form-select form-select-sm" aria-label="Filter by project">
            <option value="">All projects</option>
            @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach
        </select>
        <select id="platformFilter" class="form-select form-select-sm">
            <option value="">All platforms</option>
            @foreach(['facebook','instagram','linkedin','tiktok','twitter','pinterest','youtube'] as $provider)
                <option value="{{ $provider }}">{{ ucfirst($provider) }}</option> 
            @endforeach
        </select>
        <select id="statusFilter" class="form-select form-select-sm">
            <option value="">All statuses</option>
            @foreach(['draft','pending','queued','publishing','published','failed','cancelled'] as $status)
                <option value="{{ $status }}">{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="calendar-legend" aria-label="Post status legend"><span class="status-badge status-draft">Draft</span><span class="status-badge status-pending">Pending</span><span class="status-badge status-published">Published</span><span class="status-badge status-failed">Failed</span></div>
    <div id="schedulerCalendar" data-events-url="{{ route('calendar.events') }}"></div>
</div>

<div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="postModalTitle">Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="postModalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="{{ asset('js/social-calendar.js') }}?v={{ filemtime(public_path('js/social-calendar.js')) }}"></script>
@endpush
