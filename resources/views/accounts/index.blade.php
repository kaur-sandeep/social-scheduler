@extends('layouts.app')

@section('title', 'Accounts')
@section('subtitle', 'Connected social accounts and managed pages')

@section('content')
<div class="panel mb-4">
    <form method="get" class="mb-3"><label class="form-label">Project</label><select class="form-select" name="project_id" onchange="this.form.submit()">@foreach($projects as $item)<option value="{{ $item->id }}" @selected($item->id === $project?->id)>{{ $item->name }}</option>@endforeach</select></form>
    <div class="panel-header">
        <div>
            <h2>Providers</h2>
            <p>Connect channels and sync managed pages</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-primary" href="{{ route('facebook.redirect', ['project_id' => $project?->id]) }}"><i class="bi bi-facebook"></i> Connect Facebook</a>
            <a class="btn btn-sm btn-danger" href="{{ route('youtube.redirect', ['project_id' => $project?->id]) }}"><i class="bi bi-youtube"></i> Connect YouTube</a>
            <a class="btn btn-sm btn-primary" href="{{ route('linkedin.redirect', ['project_id' => $project?->id]) }}"><i class="bi bi-linkedin"></i> Connect LinkedIn</a>
            <a class="btn btn-sm btn-dark" href="{{ route('twitter.redirect', ['project_id' => $project?->id]) }}">Connect X</a>
            <a class="btn btn-sm btn-danger" href="{{ route('pinterest.redirect', ['project_id' => $project?->id]) }}"><i class="bi bi-pinterest"></i> Connect Pinterest</a>
            <a class="btn btn-sm btn-dark" href="{{ route('tiktok.redirect', ['project_id' => $project?->id]) }}">Connect TikTok</a>
        </div>
    </div>
</div>

@forelse($accounts as $account)
    <div class="panel mb-3">
        <div class="panel-header">
            <div>
                <h2>{{ $account->name }} <span class="badge status-badge">{{ $account->status }}</span></h2>
                <p>{{ ucfirst($account->provider) }} account connected {{ optional($account->connected_at)->diffForHumans() }}</p>
            </div>
            <form method="post" action="{{ route($account->provider.'.disconnect', $account) }}">
                @csrf
                <button class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Disconnect account" data-confirm-message="Are you sure you want to disconnect this account?"><i class="bi bi-link-45deg"></i> Disconnect</button>
            </form>
        </div>
        <div class="row g-3">
            @foreach($account->pages as $page)
                <div class="col-md-6 col-xl-4">
                    <div class="page-card">
                        @if($page->profile_image)
                            <img src="{{ $page->profile_image }}" alt="">
                        @endif
                        <div>
                            <strong>{{ $page->page_name }}</strong>
                            <p>{{ $page->category ?? 'Page' }} - {{ $page->status }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@empty
    <div class="panel">
        <p class="text-muted mb-0">No social accounts connected yet.</p>
    </div>
@endforelse
@endsection
