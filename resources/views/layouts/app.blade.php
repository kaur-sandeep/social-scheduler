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
            <span>
                <strong>Social Scheduler</strong>
                <small>Publishing Operations</small>
            </span>
        </a>
        <div class="sidebar-section">Workspace</div>
        <nav class="nav flex-column gap-1">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}" href="{{ route('calendar.index') }}"><i class="bi bi-calendar3"></i> Calendar</a>
            <a class="nav-link {{ request()->routeIs('posts.create') ? 'active' : '' }}" href="{{ route('posts.create') }}"><i class="bi bi-pencil-square"></i> Create Post</a>
            <a class="nav-link {{ request()->routeIs('posts.index') ? 'active' : '' }}" href="{{ route('posts.index') }}"><i class="bi bi-list-task"></i> Scheduled Posts</a>
            <a class="nav-link {{ request()->routeIs('analytics.*') ? 'active' : '' }}" href="{{ route('analytics.index') }}"><i class="bi bi-bar-chart"></i> Analytics</a>
        </nav>
        <div class="sidebar-section">Administration</div>
        <nav class="nav flex-column gap-1">
            <a class="nav-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}"><i class="bi bi-person-badge"></i> Accounts</a>
            <a class="nav-link {{ request()->routeIs('logs.*') ? 'active' : '' }}" href="{{ route('logs.index') }}"><i class="bi bi-terminal"></i> Logs</a>
            <a class="nav-link {{ request()->routeIs('media.*') ? 'active' : '' }}" href="{{ route('media.index') }}"><i class="bi bi-images"></i> Media Library</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="sidebar-footer">
            <span class="status-pill"></span>
            <div>
                <strong>Queue Online</strong>
                <small>Scheduler checks every minute</small>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1>@yield('title', 'Dashboard')</h1>
                <p>@yield('subtitle', 'Internal social publishing operations')</p>
            </div>
            <div class="topbar-actions">
                <a class="btn btn-primary" href="{{ route('posts.create') }}"><i class="bi bi-plus-lg"></i> New Post</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-icon" title="Sign out"><i class="bi bi-box-arrow-right"></i></button>
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
