<div class="table-responsive">
    <table class="table app-table align-middle">
        <thead><tr><th>ID <i class="bi bi-arrow-down short-muted" title="Newest first"></i></th><th>Scheduled</th><th>Platform</th><th>Page</th><th>Status</th><th>Caption</th><th></th></tr></thead>
        <tbody>
        @forelse($posts as $post)
            <tr>
                <td class="text-muted">#{{ $post->id }}</td>
                <td>@if($post->scheduled_at){{ $post->scheduled_at->timezone(auth()->user()->timezone)->format('M d, Y H:i') }}@elseif($post->published_at) Published Now @else Draft @endif</td>
                <td><span class="platform-dot platform-{{ $post->platform }}"></span>{{ ucfirst($post->platform) }}</td>
                <td>{{ $post->socialPage?->page_name ?? 'Profile' }}</td>
                <td><span class="badge status-badge">{{ $post->status->value }}</span></td>
                <td>{{ Str::limit($post->message, 110) }}</td>
                <td class="text-end">
                    @if(!in_array($post->status, [\App\Enums\PostStatus::Published, \App\Enums\PostStatus::Publishing], true))<a class="btn btn-sm btn-outline-secondary" href="{{ route('posts.edit', $post) }}" title="Edit post"><i class="bi bi-pencil"></i></a>@endif
                    <form method="post" action="{{ route('posts.destroy', $post) }}">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Delete Scheduled Post" data-confirm-message="Are you sure you want to delete this scheduled post? This action can be restored later."><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted">No posts match your search.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $posts->links() }}
