@extends('layouts.app')

@section('title', 'Create Post')
@section('subtitle', 'Compose, preview, save drafts, or schedule publishing')

@section('content')
<form class="composer-layout" method="post" action="{{ route('posts.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="panel composer-card">
                <div class="panel-header">
                    <div>
                        <h2>Content</h2>
                        <p>Write the caption and attach creative assets</p>
                    </div>
                </div>

                <label class="form-label">Caption</label>
                <textarea class="form-control composer-text" name="message" maxlength="63206" required>{{ old('message') }}</textarea>

                <div class="composer-tools">
                    <button type="button" class="btn btn-light btn-sm" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
                    <button type="button" class="btn btn-light btn-sm" title="Hashtag">#</button>
                    <button type="button" class="btn btn-light btn-sm" title="Mention">@</button>
                </div>

                <label class="form-label mt-3">Media</label>
                <div class="upload-zone">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <div>
                        <strong>Upload images or videos</strong>
                        <span>JPG, PNG, WEBP, GIF, MP4, MOV, AVI, WEBM</span>
                    </div>
                    <input class="form-control" type="file" name="media[]" multiple accept="image/*,video/*">
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="panel composer-card sticky-panel">
                <div class="panel-header">
                    <div>
                        <h2>Publishing</h2>
                        <p>Choose the destination and schedule</p>
                    </div>
                </div>

                <label class="form-label">Platform</label>
                <select class="form-select" name="platform" required>
                    @foreach(['facebook','instagram','linkedin','tiktok','twitter','pinterest','youtube','threads'] as $provider)
                        <option value="{{ $provider }}">{{ ucfirst($provider) }}</option>
                    @endforeach
                </select>

                <label class="form-label mt-3">Page/Profile</label>
                <select class="form-select" name="social_page_id">
                    <option value="">Select profile/page</option>
                    @foreach($pages as $page)
                        <option value="{{ $page->id }}">{{ ucfirst($page->provider) }} - {{ $page->page_name }}</option>
                    @endforeach
                </select>

                <div class="row g-2 mt-2">
                    <div class="col">
                        <label class="form-label">Date</label>
                        <input class="form-control" type="date" name="scheduled_date" value="{{ old('scheduled_date') }}">
                    </div>
                    <div class="col">
                        <label class="form-label">Time</label>
                        <input class="form-control" type="time" name="scheduled_time" value="{{ old('scheduled_time') }}">
                    </div>
                </div>

                <label class="form-label mt-3">Timezone</label>
                <input class="form-control" name="timezone" value="{{ old('timezone', auth()->user()->timezone ?? config('app.timezone')) }}" required>

                <div class="publish-actions">
                    <button class="btn btn-outline-secondary" name="action" value="draft"><i class="bi bi-save"></i> Save Draft</button>
                    <button class="btn btn-primary" name="action" value="schedule"><i class="bi bi-calendar-plus"></i> Schedule</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
