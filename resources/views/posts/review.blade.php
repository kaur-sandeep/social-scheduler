@extends('layouts.app')

@section('title', 'Review Posts')
@section('subtitle', 'Review every post in the same workflow before confirming publication.')

@section('content')
<div class="wizard-progress mb-4">@foreach(['Project', 'Platform', 'Destination', 'Create Posts', 'Review'] as $number => $label)<div class="wizard-progress-item active" data-progress="{{ $number + 1 }}"><span>{{ $number < 4 ? '✓' : $number + 1 }}</span><strong>{{ $label }}</strong></div>@endforeach</div>

<div class="wizard-shell">
    <section class="wizard-step review-step">
        <div class="wizard-step-heading"><span>Step 5</span><h2>Review your posts</h2><p>Confirm each caption, media selection, destination, and publishing choice before continuing.</p></div>

        <div class="review-toolbar"><div><strong>{{ $posts->count() }} {{ \Illuminate\Support\Str::plural('post', $posts->count()) }}</strong><span>saved in this workflow</span></div><a class="btn btn-outline-primary" href="{{ route('posts.create') }}"><i class="bi bi-plus-lg"></i> Create More Posts</a></div>

        <div class="review-publishes">
            @foreach($posts as $index => $post)
                @php
                    $status = match ($post->status->value) { 'draft' => 'Draft', 'pending' => 'Scheduled', 'queued', 'publishing' => 'Publish Now', default => ucfirst($post->status->value) };
                    $editUrl = route('posts.edit', $post).'?'.http_build_query(['review_posts' => $ids->all()]);
                @endphp
                <article class="review-publish review-post-card">
                    <div class="review-card-header"><div><span class="preview-label">Post #{{ $index + 1 }}</span><h3>{{ ucfirst($post->platform) }} <span>·</span> {{ $post->socialPage?->page_name ?? 'Profile' }}</h3></div><a class="btn btn-outline-primary btn-sm" href="{{ $editUrl }}"><i class="bi bi-pencil"></i> Edit</a></div>
                    <div class="review-card-grid"><div><div class="review-caption-label">Content preview</div><p class="text-break review-caption">{{ $post->message }}</p></div><div class="review-meta"><span><i class="bi bi-broadcast"></i> {{ ucfirst($post->platform) }}</span><span><i class="bi bi-person-square"></i> {{ $post->socialPage?->page_name ?? 'Profile' }}</span><span><i class="bi bi-flag"></i> {{ $status }}</span></div></div>
                    @foreach($post->media as $media)<div class="review-preview-media">@if($media->media_type === 'video')<video controls src="{{ asset('storage/'.$media->path) }}"></video>@else<img src="{{ asset('storage/'.$media->path) }}" alt="Media for post #{{ $index + 1 }}">@endif</div>@endforeach
                    <div class="review-publish-time"><strong>{{ $post->scheduled_at ? 'Scheduled for '. $post->scheduled_at->timezone($post->timezone)->format('d M Y, h:i A T') : ($post->status->value === 'draft' ? 'Saved as draft' : 'Ready to publish after confirmation') }}</strong>@if($post->scheduled_at)<span>UTC: {{ $post->scheduled_at->utc()->format('d M Y, h:i A') }} UTC</span>@endif</div>
                </article>
            @endforeach
        </div>
    </section>
    <div class="wizard-footer"><a class="btn btn-outline-secondary" href="{{ route('posts.create') }}"><i class="bi bi-arrow-left"></i> Back to Create Posts</a>@if($posts->contains(fn ($post) => $post->status->value === 'queued'))<form method="post" action="{{ route('posts.review.publish') }}">@csrf @foreach($ids as $id)<input type="hidden" name="posts[]" value="{{ $id }}">@endforeach<button class="btn btn-primary"><i class="bi bi-send"></i> Confirm &amp; Publish</button></form>@endif</div>
</div>
@endsection
