@extends('layouts.app')
@section('title', 'Analytics Overview')
@section('subtitle', 'Combined Meta account performance · '.$since.' to '.$until)
@section('content')
@php
    $totalFollowers = collect($reports)->sum(fn ($report) => data_get($report, 'data.account.followers_count', data_get($report, 'data.account.fan_count', 0)));
    $metricTotal = fn ($name) => collect($reports)->sum(fn ($report) => collect(data_get($report, 'data.insights', []))->where('name', $name)->sum(fn ($metric) => collect($metric['values'] ?? [])->sum(fn ($point) => is_numeric($point['value'] ?? null) ? $point['value'] : 0)));
    $contentTotal = collect($reports)->sum(fn ($report) => count($report['data']['media'] ?? []));
@endphp
<style>.overview-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px}.overview-card{background:#fff;border:1px solid #e7ebf0;border-radius:12px;padding:18px}.overview-card small{color:#667085}.overview-card strong{display:block;font-size:1.6rem;margin:6px 0}</style>
<div class="panel mb-3"><form method="get" class="d-flex flex-wrap align-items-end gap-3"><div><label class="form-label">Platform</label><select class="form-select" name="platform"><option value="all" @selected($platform==='all')>All Platforms</option><option value="facebook" @selected($platform==='facebook')>Facebook</option><option value="instagram" @selected($platform==='instagram')>Instagram</option></select></div><div><label class="form-label">Period</label><select class="form-select" name="range">@foreach(['today'=>'Today','yesterday'=>'Yesterday','last_7_days'=>'Last 7 Days','last_30_days'=>'Last 30 Days','this_month'=>'This Month','last_month'=>'Last Month'] as $key=>$label)<option value="{{ $key }}" @selected($range===$key)>{{ $label }}</option>@endforeach</select></div><button class="btn btn-primary">Apply</button></form></div>
<div class="alert alert-info">Combined audience is the sum of connected account follower totals. It does not represent unique people. Reach and views remain separated by platform and account.</div>
<div class="overview-grid"><div class="overview-card"><small>Combined audience · account sum</small><strong>{{ number_format($totalFollowers) }}</strong><small>{{ count($reports) }} account reports loaded</small></div><div class="overview-card"><small>Facebook views</small><strong>{{ number_format($metricTotal('page_media_view')) }}</strong><small>Metric totals across Facebook accounts</small></div><div class="overview-card"><small>Instagram views</small><strong>{{ number_format($metricTotal('views')) }}</strong><small>Metric totals across Instagram accounts</small></div><div class="overview-card"><small>Content in period</small><strong>{{ number_format($contentTotal) }}</strong><small>Published items returned by Meta</small></div></div>
<div class="row g-3">
@forelse($reports as $report)
    <div class="col-12 col-xl-6"><div class="panel h-100">
        <div class="d-flex justify-content-between align-items-start"><div><h2>{{ $report['platform']==='facebook' ? 'Facebook' : 'Instagram' }}</h2><div class="text-muted">{{ $report['platform']==='instagram' ? '@'.($report['page']->instagram_username ?: $report['page']->page_name) : $report['page']->page_name }}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('accounts.analytics',['page'=>$report['page'],'platform'=>$report['platform'],'range'=>$range]) }}">Open report</a></div>
        @if($report['error'])<div class="alert alert-warning mt-3">{{ $report['error'] }}</div>@else
            @php($followerCount = data_get($report,'data.account.followers_count',data_get($report,'data.account.fan_count')))
            <div class="d-flex flex-wrap gap-4 mt-3"><span>Followers{{ $report['platform']==='facebook' ? ' / page likes' : '' }}: <strong>{{ is_numeric($followerCount) ? number_format($followerCount) : 'Not available' }}</strong></span><span>Content: <strong>{{ count($report['data']['media']) }}</strong></span></div>
            <div class="d-flex flex-wrap gap-2 mt-3">@foreach($report['data']['insights'] as $insight)<span class="badge text-bg-light">{{ str($insight['title'] ?? $insight['name'])->replace('_',' ')->title() }}: {{ data_get($insight,'values.0.value','Not available') }}</span>@endforeach</div>
        @endif
    </div></div>
@empty
    <div class="col-12"><div class="panel text-muted">No active Facebook or Instagram accounts are connected for this user.</div></div>
@endforelse
</div>
<div class="text-muted small mt-3">Generated {{ now()->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('M d, Y h:i A') }}. Metric availability depends on account type, Graph API version, and granted permissions.</div>
@endsection
