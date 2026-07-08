@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Publishing health, upcoming work, and recent activity')

@section('content')
<div class="metric-grid">
    @foreach($metrics as $label => $value)
        <div class="metric">
            <span>{{ Str::headline($label) }}</span>
            <strong>{{ number_format($value) }}</strong>
        </div>
    @endforeach
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <div class="panel">
            <div class="panel-header">
                <h2>Upcoming Posts</h2>
                <a href="{{ route('calendar.index') }}">Open calendar</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Time</th><th>Platform</th><th>Page</th><th>Status</th><th>Caption</th></tr></thead>
                    <tbody>
                    @forelse($upcoming as $post)
                        <tr>
                            <td>{{ optional($post->scheduled_at)->timezone(auth()->user()->timezone)->format('M d, H:i') }}</td>
                            <td><span class="platform-dot platform-{{ $post->platform }}"></span>{{ ucfirst($post->platform) }}</td>
                            <td>{{ $post->socialPage?->page_name ?? 'Profile' }}</td>
                            <td><span class="badge text-bg-light">{{ $post->status->value }}</span></td>
                            <td>{{ Str::limit($post->message, 90) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No upcoming posts yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="panel">
            <div class="panel-header"><h2>Right Sidebar</h2></div>
            @foreach($recent as $post)
                <div class="side-item">
                    <span class="platform-dot platform-{{ $post->platform }}"></span>
                    <div>
                        <strong>{{ ucfirst($post->platform) }} · {{ $post->status->value }}</strong>
                        <p>{{ Str::limit($post->message, 70) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
