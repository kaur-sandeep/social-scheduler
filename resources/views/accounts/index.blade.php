@extends('layouts.app')

@section('title', 'Accounts')
@section('subtitle', 'Connected social accounts and managed pages')

@section('content')
<div class="panel mb-4">
    <div class="panel-header">
        <h2>Providers</h2>
        <a class="btn btn-sm btn-primary" href="{{ route('facebook.redirect') }}"><i class="bi bi-facebook"></i> Connect Facebook</a>
    </div>
    <div class="provider-grid">
        @foreach(['facebook','instagram','linkedin','tiktok','twitter','pinterest','youtube','threads'] as $provider)
            <div class="provider-card">
                <span class="platform-dot platform-{{ $provider }}"></span>
                <strong>{{ ucfirst($provider) }}</strong>
                <small>{{ $provider === 'facebook' ? 'OAuth ready' : 'Extension point ready' }}</small>
            </div>
        @endforeach
    </div>
</div>

@foreach($accounts as $account)
    <div class="panel mb-3">
        <div class="panel-header">
            <h2>{{ $account->name }} <span class="badge text-bg-light">{{ $account->status }}</span></h2>
            <form method="post" action="{{ route('facebook.disconnect', $account) }}">
                @csrf
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-link-45deg"></i> Disconnect</button>
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
                            <p>{{ $page->category ?? 'Page' }} · {{ $page->status }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
@endsection
