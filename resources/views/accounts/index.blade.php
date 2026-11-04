@extends('layouts.app')

@section('title', 'Accounts')
@section('subtitle', 'Connected social accounts and managed pages')

@section('content')
@php
    $providers = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'bi-facebook', 'class' => 'btn-primary'],
        'instagram' => ['label' => 'Instagram', 'icon' => 'bi-instagram', 'class' => 'btn-danger'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'bi-youtube', 'class' => 'btn-danger'],
        'linkedin' => ['label' => 'LinkedIn', 'icon' => 'bi-linkedin', 'class' => 'btn-primary'],
        'twitter' => ['label' => 'X', 'icon' => 'bi-twitter-x', 'class' => 'btn-dark'],
        'pinterest' => ['label' => 'Pinterest', 'icon' => 'bi-pinterest', 'class' => 'btn-danger'],
        'tiktok' => ['label' => 'TikTok', 'icon' => 'bi-tiktok', 'class' => 'btn-dark'],
    ];
    $connectedProviders = $accounts->where('status', 'active')->pluck('provider')->unique();
@endphp
<div class="panel mb-4">
    <form method="get" class="mb-3"><label class="form-label">Project</label><select class="form-select" name="project_id" onchange="this.form.submit()">@foreach($projects as $item)<option value="{{ $item->id }}" @selected($item->id === $project?->id)>{{ $item->name }}</option>@endforeach</select></form>
    <div class="panel-header">
        <div>
            <h2>Providers</h2>
            <p>Connect channels and sync managed pages</p>
        </div>
        <div class="d-flex gap-2">
            @foreach($providers as $provider => $details)
                @if($provider === 'instagram' && $project)
                    <a class="btn btn-sm {{ $details['class'] }}" href="{{ route('instagram.redirect', ['project_id' => $project->id]) }}"><i class="bi {{ $details['icon'] }}"></i> {{ $accounts->where('provider', 'instagram')->isNotEmpty() ? 'Reconnect Instagram' : 'Connect Instagram' }}</a>
                @elseif($connectedProviders->contains($provider))
                    <button class="btn btn-sm {{ $details['class'] }}" type="button" disabled title="{{ $details['label'] }} is already connected to this project">
                        <i class="bi {{ $details['icon'] }}"></i> {{ $details['label'] === 'YouTube' ? 'YouTube Connected' : $details['label'].' connected' }}
                    </button>
                @else
                    <a class="btn btn-sm {{ $details['class'] }}" href="{{ route($provider.'.redirect', ['project_id' => $project?->id]) }}">
                        <i class="bi {{ $details['icon'] }}"></i> Connect {{ $details['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>

@forelse($accounts as $account)
    <div class="panel mb-3">
        <div class="panel-header">
            <div>
                <h2><i class="bi {{ $providers[$account->provider]['icon'] ?? 'bi-share-fill' }} account-provider-icon" aria-hidden="true"></i> {{ $account->name }} <span class="badge status-badge">{{ $account->status }}</span></h2>
                <p>{{ $account->provider === 'youtube' ? 'YouTube Connected' : ucfirst($account->provider).' account connected' }} {{ optional($account->connected_at)->diffForHumans() }}</p>
            </div>
            <form method="post" action="{{ route($account->provider.'.disconnect', $account) }}">
                @csrf
                <button class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Disconnect account" data-confirm-message="Are you sure you want to disconnect this account?"> <i class="bi bi-link-45deg"></i> {{ $account->provider === 'youtube' ? 'Disconnect YouTube' : 'Disconnect' }}</button>
            </form>
            @if($account->provider === 'instagram' && in_array($account->status, ['active', 'reauthorization_required'], true) && $account->pages->contains(fn ($page) => $page->status === 'active'))
                @php($instagramPage = $account->pages->first(fn ($page) => $page->status === 'active'))
                <a class="btn btn-sm btn-outline-danger" href="{{ route('accounts.analytics', ['page' => $instagramPage, 'platform' => 'instagram', 'project_id' => $project?->id]) }}"><i class="bi bi-bar-chart-line"></i> Instagram Professional Dashboard</a>
            @endif
            @if($account->provider === 'linkedin')<a class="btn btn-sm btn-outline-primary" href="{{ route('linkedin.redirect', ['project_id' => $project?->id]) }}"><i class="bi bi-arrow-repeat"></i> Reconnect &amp; Sync Pages</a>@endif
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
                            @if($page->provider === 'instagram')<p class="small text-muted mb-1">Instagram Login · metrics sync {{ $page->last_synced_at?->diffForHumans() ?? 'pending' }}</p>@endif
                            @if($account->provider === 'facebook' && $account->status === 'active' && $page->status === 'active')
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('accounts.analytics', ['page' => $page, 'platform' => 'facebook', 'project_id' => $project?->id]) }}"><i class="bi bi-bar-chart-line"></i> Facebook Professional Dashboard</a>
                                    @if($page->instagram_business_id && $page->instagram_username)<a class="btn btn-sm btn-outline-danger" href="{{ route('accounts.analytics', ['page' => $page, 'platform' => 'instagram', 'project_id' => $project?->id]) }}"><i class="bi bi-instagram"></i> Instagram Professional Dashboard</a>@endif
                                    @if(!$page->instagram_business_id)<a class="btn btn-sm btn-outline-danger" href="{{ route('instagram.redirect', ['project_id' => $project?->id]) }}"><i class="bi bi-instagram"></i> Connect Instagram</a>@endif
                                </div>
                            @endif
                            @if($account->provider === 'instagram' && in_array($account->status, ['active', 'reauthorization_required'], true) && $page->status === 'active')
                                <div class="d-flex flex-wrap gap-2 mt-2"><a class="btn btn-sm btn-outline-danger" href="{{ route('accounts.analytics', ['page' => $page, 'platform' => 'instagram', 'project_id' => $project?->id]) }}"><i class="bi bi-bar-chart-line"></i> Instagram Professional Dashboard</a></div>
                            @endif
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
