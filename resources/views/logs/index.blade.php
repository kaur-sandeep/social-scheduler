@extends('layouts.app')

@section('title', 'Logs')
@section('subtitle', 'Provider requests, responses, and publish diagnostics')

@section('content')
<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Time</th><th>Platform</th><th>Endpoint</th><th>Status</th><th>Duration</th><th>Result</th></tr></thead>
            <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('M d, H:i:s') }}</td>
                    <td>{{ ucfirst($log->platform) }}</td>
                    <td><code>{{ $log->endpoint }}</code></td>
                    <td>{{ $log->status_code }}</td>
                    <td>{{ $log->execution_time_ms }} ms</td>
                    <td><span class="badge {{ $log->success ? 'text-bg-success' : 'text-bg-danger' }}">{{ $log->success ? 'Success' : 'Failed' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection
