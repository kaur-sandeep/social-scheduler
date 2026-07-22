<div class="table-responsive">
    <table class="table app-table align-middle">
        @php $sort = request('sort', 'id'); $direction = request('direction', 'desc'); $sortUrl = function ($field) use ($sort, $direction) { return request()->fullUrlWithQuery(['sort' => $field, 'direction' => $sort === $field && $direction === 'asc' ? 'desc' : 'asc', 'page' => null]); }; @endphp
        <thead><tr>
            @foreach(['id' => 'ID', 'scheduled' => 'Scheduled', 'platform' => 'Platform', 'project' => 'Project', 'page' => 'Page', 'status' => 'Status', 'published' => 'Published At'] as $field => $label)
                <th><a class="table-sort" href="{{ $sortUrl($field) }}">{{ $label }} @if($sort === $field)<span aria-label="Sorted {{ $direction }}">{{ $direction === 'asc' ? '▲' : '▼' }}</span>@else<span class="sort-muted">▲▼</span>@endif</a></th>
            @endforeach
            <th class="text-end">Action</th></tr></thead>
        <tbody>
        @forelse($posts as $post)
            <tr>
                <td class="text-muted">#{{ $post->id }}</td>
                <td>@if($post->scheduled_at)<time class="scheduled-date status-text-{{ $post->status->value }} localized-datetime" datetime="{{ $post->scheduled_at->toIso8601String() }}" data-local-date="{{ $post->scheduled_at->toIso8601String() }}">{{ $post->scheduled_at->timezone($post->timezone ?: auth()->user()->timezone ?: 'UTC')->format('M d, Y h:i A') }}</time>@else - @endif</td>
                <td>@php($icon = ['facebook' => 'facebook', 'instagram' => 'instagram', 'linkedin' => 'linkedin', 'twitter' => 'twitter-x', 'tiktok' => 'tiktok', 'youtube' => 'youtube', 'pinterest' => 'pinterest', 'threads' => 'threads'][$post->platform] ?? 'share')<i class="bi bi-{{ $icon }} platform-icon platform-{{ $post->platform }}" aria-hidden="true"></i>{{ ucfirst($post->platform) }}</td>
                <td>{{ $post->project?->name ?? '-' }}</td>
                <td>{{ $post->socialPage?->page_name ?? 'Profile' }}</td>
                <td><span class="badge status-badge status-{{ $post->status->value }}">{{ $post->status->value }}</span></td>
                <td>@if($post->published_at)<time class="localized-datetime" datetime="{{ $post->published_at->toIso8601String() }}" data-local-date="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->timezone($post->timezone ?: auth()->user()->timezone ?: 'UTC')->format('M d, Y h:i A') }}</time>@else - @endif</td>
                <td class="text-end"><div class="post-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-view-post data-post-id="{{ $post->id }}" data-project="{{ $post->project?->name ?? '-' }}" data-platform="{{ ucfirst($post->platform) }}" data-destination="{{ $post->socialPage?->page_name ?? 'Profile' }}" data-status="{{ $post->status->value }}" data-scheduled-at="{{ $post->scheduled_at?->toIso8601String() }}" data-published-at="{{ $post->published_at?->toIso8601String() }}" data-caption="{{ $post->message }}" data-media="{{ json_encode($post->media->map(fn ($media) => ['type' => $media->media_type, 'url' => asset('storage/'.$media->path)])->values()) }}"><i class="bi bi-eye"></i> View</button>
                    @if(!in_array($post->status, [\App\Enums\PostStatus::Published, \App\Enums\PostStatus::Publishing], true))<a class="btn btn-sm btn-outline-secondary" href="{{ route('posts.edit', $post) }}" title="Edit post"><i class="bi bi-pencil"></i> Edit</a>@endif
                    <form method="post" action="{{ route('posts.destroy', $post) }}">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Delete Scheduled Post" data-confirm-message="Are you sure you want to delete this scheduled post? This action can be restored later."><i class="bi bi-trash"></i> Delete</button></form>
                </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-muted">No posts match your search.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $posts->links() }}
