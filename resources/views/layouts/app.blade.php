<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Social Scheduler') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    @stack('styles')
    <link href="{{ asset('css/social-scheduler.css') }}" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">SS</span>
            <span>Social Scheduler</span>
        </a>
        <nav class="nav flex-column gap-1">
            <a class="nav-link" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link" href="{{ route('calendar.index') }}"><i class="bi bi-calendar3"></i> Calendar</a>
            <a class="nav-link" href="{{ route('accounts.index') }}"><i class="bi bi-person-badge"></i> Accounts</a>
            <a class="nav-link" href="{{ route('posts.create') }}"><i class="bi bi-pencil-square"></i> Create Post</a>
            <a class="nav-link" href="{{ route('posts.index') }}"><i class="bi bi-list-task"></i> Scheduled Posts</a>
            <a class="nav-link" href="{{ route('analytics.index') }}"><i class="bi bi-bar-chart"></i> Analytics</a>
            <a class="nav-link" href="{{ route('logs.index') }}"><i class="bi bi-terminal"></i> Logs</a>
            <a class="nav-link" href="{{ route('media.index') }}"><i class="bi bi-images"></i> Media Library</a>
            <a class="nav-link" href="{{ route('settings.index') }}"><i class="bi bi-gear"></i> Settings</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1>@yield('title', 'Dashboard')</h1>
                <p>@yield('subtitle', 'Internal social publishing operations')</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="{{ route('posts.create') }}"><i class="bi bi-plus-lg"></i> New Post</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
