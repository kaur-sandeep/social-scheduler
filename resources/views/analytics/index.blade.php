@extends('layouts.app')
@section('title', 'Analytics Overview')
@section('subtitle', 'Combined Meta account performance · '.$since.' to '.$until)
@section('content')
@php
    $metricValue = fn ($metric) => is_numeric(data_get($metric, 'total_value.value'))
        ? data_get($metric, 'total_value.value')
        : collect($metric['values'] ?? [])->sum(fn ($point) => is_numeric($point['value'] ?? null) ? $point['value'] : 0);
    $reportFollowers = function ($report) use ($metricValue) {
        $followers = data_get($report, 'data.account.followers_count');
        if (($report['platform'] ?? null) === 'instagram') {
            $followerInsight = collect(data_get($report, 'data.insights', []))->firstWhere('name', 'follower_count');
            $insightFollowers = $followerInsight ? $metricValue($followerInsight) : null;
            if (is_numeric($insightFollowers) && $insightFollowers > 0) return $insightFollowers;
        }
        if (is_numeric($followers)) return $followers;
        if (($report['platform'] ?? null) === 'instagram' && isset($insightFollowers) && is_numeric($insightFollowers)) return $insightFollowers;
        return null;
    };
    $facebookFollowers = collect($reports)->filter(fn ($report) => ($report['platform'] ?? null) === 'facebook')->sum(fn ($report) => is_numeric($reportFollowers($report)) ? $reportFollowers($report) : 0);
    $instagramFollowers = collect($reports)->filter(fn ($report) => ($report['platform'] ?? null) === 'instagram')->sum(fn ($report) => is_numeric($reportFollowers($report)) ? $reportFollowers($report) : 0);
    $metricTotal = fn ($name) => collect($reports)->sum(fn ($report) => collect(data_get($report, 'data.insights', []))->where('name', $name)->sum(fn ($metric) => $metricValue($metric)));
    $contentTotal = collect($reports)->sum(fn ($report) => count($report['data']['media'] ?? []));
@endphp
<style>.overview-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px}.overview-card{background:#fff;border:1px solid #e7ebf0;border-radius:12px;padding:18px}.overview-card small{color:#667085}.overview-card strong{display:block;font-size:1.6rem;margin:6px 0}</style>
<div class="panel mb-3"><form method="get" class="d-flex flex-wrap align-items-end gap-3"><div><label class="form-label">Platform</label><select class="form-select" name="platform"><option value="all" @selected($platform==='all')>All Platforms</option><option value="facebook" @selected($platform==='facebook')>Facebook</option><option value="instagram" @selected($platform==='instagram')>Instagram</option></select></div><div><label class="form-label">Period</label><select class="form-select" name="range">@foreach(['today'=>'Today','yesterday'=>'Yesterday','last_7_days'=>'Last 7 Days','last_30_days'=>'Last 30 Days','this_month'=>'This Month','last_month'=>'Last Month'] as $key=>$label)<option value="{{ $key }}" @selected($range===$key)>{{ $label }}</option>@endforeach</select></div><button class="btn btn-primary">Apply</button></form></div>
<div class="alert alert-info">Combined audience is the sum of connected account follower totals. It does not represent unique people. Reach and views remain separated by platform and account.</div>
<div class="overview-grid"><div class="overview-card"><small>Facebook followers</small><strong>{{ number_format($facebookFollowers) }}</strong><small>Current followers across connected Facebook Pages</small></div><div class="overview-card"><small>Facebook views · selected period</small><strong>{{ number_format($metricTotal('page_media_view')) }}</strong><small>Metric totals across Facebook accounts</small></div><div class="overview-card"><small>Instagram followers</small><strong>{{ number_format($instagramFollowers) }}</strong><small>Current followers across connected Instagram accounts</small></div><div class="overview-card"><small>Instagram views · selected period</small><strong>{{ number_format($metricTotal('views')) }}</strong><small>Metric totals across Instagram accounts</small></div><div class="overview-card"><small>Content in period</small><strong>{{ number_format($contentTotal) }}</strong><small>Published items returned by Meta</small></div></div>
<div class="row g-3">
@forelse($reports as $report)
    <div class="col-12 col-xl-6"><div class="panel h-100">
        <div class="d-flex justify-content-between align-items-start"><div><h2>{{ $report['platform']==='facebook' ? 'Facebook' : 'Instagram' }}</h2><div class="text-muted">{{ $report['platform']==='instagram' ? '@'.($report['page']->instagram_username ?: $report['page']->page_name) : $report['page']->page_name }}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('accounts.analytics',['page'=>$report['page'],'platform'=>$report['platform'],'range'=>$range]) }}">Open report</a></div>
        @if($report['error'])<div class="alert alert-warning mt-3">{{ $report['error'] }}</div>@else
            <div class="d-flex flex-wrap gap-4 mt-3"><span>Followers: <strong>{{ is_numeric($reportFollowers($report)) ? number_format($reportFollowers($report)) : 'Not available' }}</strong></span><span>Content: <strong>{{ count($report['data']['media']) }}</strong></span></div>
            <div class="d-flex flex-wrap gap-2 mt-3">@foreach($report['data']['insights'] as $insight)<span class="badge text-bg-light">{{ str($insight['title'] ?? $insight['name'])->replace('_',' ')->title() }} · selected period: {{ is_numeric($metricValue($insight)) ? number_format($metricValue($insight)) : 'Not available' }}</span>@endforeach</div>
        @endif
    </div></div>
@empty
    <div class="col-12"><div class="panel text-muted">No active Facebook or Instagram accounts are connected for this user.</div></div>
@endforelse
</div>
<div class="text-muted small mt-3">Generated {{ now()->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('M d, Y h:i A') }}. Metric availability depends on account type, Graph API version, and granted permissions.</div>
@endsection
