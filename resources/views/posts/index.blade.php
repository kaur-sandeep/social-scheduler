@extends('layouts.app')

@section('title', 'Scheduled Posts')
@section('subtitle', 'Drafts, queued posts, publishing history, and failures')

@section('content')
<div class="panel">
    <div class="table-responsive">
        <table class="table app-table align-middle">
            <thead><tr><th>Scheduled</th><th>Platform</th><th>Page</th><th>Status</th><th>Caption</th><th></th></tr></thead>
            <tbody>
            @forelse($posts as $post)
                <tr>
                    <td>
                        @if($post->scheduled_at)
                            {{ $post->scheduled_at->timezone(auth()->user()->timezone)->format('M d, Y H:i') }}
                        @elseif($post->published_at)
                            Published Now
                        @else
                            Draft
                        @endif
                    </td>
                    <td>
                        <span class="platform-dot platform-{{ $post->platform }}"></span>{{ ucfirst($post->platform) }}</td>
                    <td>{{ $post->socialPage?->page_name ?? 'Profile' }}</td>
                    <td><span class="badge status-badge">{{ $post->status->value }}</span></td>
                    <td>{{ Str::limit($post->message, 110) }}</td>
                    <td class="text-end">
                        <form method="post" action="{{ route('posts.destroy', $post) }}">
                            @csrf
                            @method('delete')
                            <button class="btn btn-sm btn-outline-danger" data-confirm-delete><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No posts created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $posts->links() }}
</div>
@endsection
