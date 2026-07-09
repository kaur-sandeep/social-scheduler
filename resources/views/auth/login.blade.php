<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in - {{ config('app.name', 'Social Scheduler') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/social-scheduler.css') }}" rel="stylesheet">
</head>
<body class="login-body">
<main class="login-panel">
    <div class="brand text-dark mb-4">
        <span class="brand-mark">SS</span>
        <span>
            <strong>Social Scheduler</strong>
            <small>Publishing Operations</small>
        </span>
    </div>
    <h1>Sign in</h1>
    <p class="text-muted">Use your internal administrator account.</p>

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <label class="form-label">Email</label>
        <input class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label class="form-label">Password</label>
        <input class="form-control mb-3" type="password" name="password" required>

        <label class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" value="1">
            <span class="form-check-label">Remember this browser</span>
        </label>

        <button class="btn btn-primary w-100">Sign in</button>
    </form>
</main>
</body>
</html>
