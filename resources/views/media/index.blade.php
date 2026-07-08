@extends('layouts.app')

@section('title', 'Media Library')
@section('subtitle', 'Uploaded images, videos, thumbnails, and file metadata')

@section('content')
<div class="panel">
    <div class="panel-header">
        <h2>Library</h2>
        <a class="btn btn-sm btn-primary" href="{{ route('posts.create') }}"><i class="bi bi-upload"></i> Upload in Composer</a>
    </div>
    <p class="text-muted mb-0">Media is stored under <code>storage/app/public/posts</code> and attached to posts with MIME type, size, and display order metadata.</p>
</div>
@endsection
