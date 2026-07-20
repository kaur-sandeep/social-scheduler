@extends('layouts.app')

@section('title', 'Accounts')
@section('subtitle', 'Connected social accounts and managed pages')

@section('content')
@php
    $providers = [
        'facebook' => ['label' => 'Facebook', 'icon' => 'bi-facebook', 'class' => 'btn-primary'],
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
                @if($connectedProviders->contains($provider))
                    <button class="btn btn-sm {{ $details['class'] }}" type="button" disabled title="{{ $details['label'] }} is already connected to this project">
                        <i class="bi {{ $details['icon'] }}"></i> {{ $details['label'] }} connected
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
