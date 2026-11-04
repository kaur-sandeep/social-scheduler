@extends('layouts.app')
@section('title', ucfirst($platform).' Analytics')
@section('subtitle', $platform === 'instagram' ? '@'.($page->instagram_username ?: $page->page_name) : $page->page_name)
@section('content')
@php
    $metrics = collect($data['insights'] ?? [])->keyBy('name');
    $pointMap = fn ($name) => collect(data_get($metrics->get($name), 'values', []))->mapWithKeys(fn ($p) => [\Illuminate\Support\Carbon::parse($p['end_time'] ?? $since)->toDateString() => $p['value'] ?? null]);
    $days = collect(); $cursor = \Illuminate\Support\Carbon::parse($since); $last = \Illuminate\Support\Carbon::parse($until);
    while ($cursor->lte($last)) { $days->push($cursor->toDateString()); $cursor->addDay(); }
    $viewsName = $platform === 'instagram' ? 'views' : 'page_media_view';
    $reachName = $platform === 'instagram' ? 'reach' : 'page_total_media_view_unique';
    $reachLabel = $platform === 'instagram' ? 'Reach' : 'Unique viewers';
    $followerName = $platform === 'instagram' ? 'follower_count' : 'page_daily_follows'; $unfollowsName = 'page_daily_unfollows';
    $viewsPoints = $pointMap($viewsName); $reachPoints = $pointMap($reachName); $followerPoints = $platform === 'facebook' ? $pointMap($followerName) : collect();
    $mediaByDay = collect($data['media'] ?? [])->groupBy(fn ($item) => !empty($item['published_at']) ? \Illuminate\Support\Carbon::parse($item['published_at'])->toDateString() : '');
    $sumMetric = fn ($name) => collect(data_get($metrics->get($name), 'values', []))->sum(fn ($p) => is_numeric($p['value'] ?? null) ? $p['value'] : 0);
    $media = collect($data['media'] ?? [])->sortByDesc('published_at')->values();
    $latestPosts = $media->take(5);
    $typeLabel = function ($type) use ($platform) {
        $type = strtoupper((string) $type);
        if (str_contains($type, 'REEL')) return 'Reel';
        if (str_contains($type, 'CAROUSEL')) return 'Carousel';
        if (str_contains($type, 'VIDEO')) return 'Video';
        if (str_contains($type, 'IMAGE') || str_contains($type, 'PHOTO')) return 'Photo';
        if ($platform === 'facebook' && str_contains($type, 'LINK')) return 'Link';
        return $platform === 'facebook' ? 'Text' : ucfirst(strtolower($type ?: 'Other'));
    };
    $contentTypes = $media->groupBy(fn ($item) => $typeLabel($item['type'] ?? ''))->map->count();
    $knownSum = fn ($items, $key, $fallback = null) => $items->contains(fn ($item) => is_numeric($item['metrics'][$key] ?? ($fallback ? ($item['metrics'][$fallback] ?? null) : null))) ? $items->sum(fn ($item) => $item['metrics'][$key] ?? ($fallback ? ($item['metrics'][$fallback] ?? 0) : 0)) : null;
    $interactionTotals = ['Likes / reactions' => $knownSum($media, 'likes', 'reactions'), 'Comments' => $knownSum($media, 'comments'), 'Shares' => $knownSum($media, 'shares'), 'Saves' => $knownSum($media, 'saved')];
    $hasEngagementData = collect($interactionTotals)->contains(fn ($value) => is_numeric($value));
    $totalEngagements = $hasEngagementData ? collect($interactionTotals)->filter(fn ($value) => is_numeric($value))->sum() : null;
    $unfollowsPoints = $platform === 'facebook' ? $pointMap($unfollowsName) : collect();
    $daily = $days->map(function ($date) use ($viewsPoints, $reachPoints, $followerPoints, $mediaByDay) {
        $items = $mediaByDay->get($date, collect());
        $sum = fn ($key) => $items->contains(fn ($item) => is_numeric($item['metrics'][$key] ?? null)) ? $items->sum(fn ($item) => is_numeric($item['metrics'][$key] ?? null) ? $item['metrics'][$key] : 0) : null;
        $likes = $sum('likes'); $reactions = $sum('reactions');
        return ['date' => $date, 'followers' => null, 'new_followers' => $followerPoints->get($date), 'views' => $viewsPoints->get($date), 'reach' => $reachPoints->get($date), 'likes' => is_numeric($likes) ? $likes : $reactions, 'comments' => $sum('comments'), 'shares' => $sum('shares'), 'posts' => $items->count()];
    });
    $reachAvailable = $metrics->has($reachName) && $daily->contains(fn ($row) => is_numeric($row['reach']));
    $kpis = [
        ['Followers', data_get($data, 'account.followers_count'), 'bi-people'],
        ['Net follower change', $platform === 'facebook' && $metrics->has('page_daily_follows') && $metrics->has($unfollowsName) ? $sumMetric('page_daily_follows') - $sumMetric($unfollowsName) : null, 'bi-person-plus'],
        ['Views', $metrics->has($viewsName) ? $sumMetric($viewsName) : null, 'bi-eye'],
        [$reachLabel, $metrics->has($reachName) ? $sumMetric($reachName) : null, 'bi-broadcast'],
        ['Likes / reactions', $interactionTotals['Likes / reactions'], 'bi-heart'],
        ['Comments', $interactionTotals['Comments'], 'bi-chat'],
        ['Shares', $interactionTotals['Shares'], 'bi-share'],
        ['Saves', $platform === 'instagram' && $media->contains(fn ($item) => array_key_exists('saved', $item['metrics'] ?? [])) ? $interactionTotals['Saves'] : null, 'bi-bookmark'],
        ['Published content', $data['published_post_count'] ?? $media->count(), 'bi-send'],
    ];
    if ($platform === 'facebook') $kpis[] = ['Page likes', data_get($data, 'account.fan_count'), 'bi-hand-thumbs-up'];
    $previousMetricCollection = collect($previous['insights'] ?? [])->keyBy('name');
    $previousGrowth = $platform === 'facebook' && $previousMetricCollection->has('page_daily_follows') && $previousMetricCollection->has($unfollowsName)
        ? collect(data_get($previousMetricCollection->get('page_daily_follows'), 'values', []))->sum(fn ($p) => is_numeric($p['value'] ?? null) ? $p['value'] : 0) - collect(data_get($previousMetricCollection->get($unfollowsName), 'values', []))->sum(fn ($p) => is_numeric($p['value'] ?? null) ? $p['value'] : 0)
        : null;
    $currentGrowth = $platform === 'facebook' && $metrics->has('page_daily_follows') && $metrics->has($unfollowsName) ? $sumMetric('page_daily_follows') - $sumMetric($unfollowsName) : null;
    $growthChange = is_numeric($currentGrowth) && is_numeric($previousGrowth) ? ($previousGrowth == 0 ? null : ($currentGrowth - $previousGrowth) / abs($previousGrowth) * 100) : null;
    $followerBreakdown = $platform === 'facebook' && $metrics->has('page_daily_follows') && $metrics->has($unfollowsName)
        ? number_format($sumMetric('page_daily_follows')).' follows · '.number_format($sumMetric($unfollowsName)).' unfollows'
        : null;
    $previousMetrics = collect($previous['insights'] ?? [])->keyBy('name');
    $previousMedia = collect($previous['media'] ?? []);
    $previousMetricSum = fn ($name) => $previousMetrics->has($name) ? collect(data_get($previousMetrics->get($name), 'values', []))->sum(fn ($p) => is_numeric($p['value'] ?? null) ? $p['value'] : 0) : null;
    $previousTotals = [
        'Views' => $previousMetricSum($viewsName), $reachLabel => $previousMetricSum($reachName),
        'Likes / reactions' => $knownSum($previousMedia, 'likes', 'reactions'),
        'Comments' => $knownSum($previousMedia, 'comments'),
        'Shares' => $knownSum($previousMedia, 'shares'),
        'Saves' => $knownSum($previousMedia, 'saved'),
        'Published content' => $previousPublishedPostCount ?? $previousMedia->count(),
    ];
    $demographicRows = collect();
    $walkDemographics = function ($node) use (&$walkDemographics, $demographicRows) {
        if (!is_array($node)) return;
        if (isset($node['dimension_values']) && is_array($node['dimension_values']) && is_numeric($node['value'] ?? null)) {
            $demographicRows->push(['dimensions' => $node['dimension_values'], 'value' => (float) $node['value']]);
        }
        foreach ($node as $child) if (is_array($child)) $walkDemographics($child);
    };
    $walkDemographics($data['demographics'] ?? []);
    $ageGroups = ['13-17','18-24','25-34','35-44','45-54','55-64','65+'];
    $ageDistribution = collect($ageGroups)->mapWithKeys(fn ($age) => [$age => $demographicRows->filter(fn ($row) => in_array($age, $row['dimensions'], true))->sum('value')])->filter(fn ($value) => $value > 0);
    $genderDistribution = $demographicRows->filter(fn ($row) => collect($row['dimensions'])->contains(fn ($value) => in_array(strtolower((string)$value), ['male','female','other','unknown','m','f'], true)))->groupBy(fn ($row) => collect($row['dimensions'])->first(fn ($value) => in_array(strtolower((string)$value), ['male','female','other','unknown','m','f'], true)))->map->sum('value');
    $genderDistribution = $genderDistribution->mapWithKeys(fn ($value, $key) => [match (strtolower((string)$key)) {'m' => 'Male', 'f' => 'Female', default => ucfirst(strtolower((string)$key))} => $value]);
    $demographicsAvailable = $ageDistribution->isNotEmpty() || $genderDistribution->isNotEmpty();
@endphp
<style>
.analytics-page{--ink:#172b4d;--muted:#667085;--line:#e6eaf0;--blue:#1877f2;color:var(--ink)}.analytics-panel{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px;margin-bottom:16px;box-shadow:0 2px 8px #16324f08}.analytics-title{font-size:1.05rem;font-weight:700;margin:0}.analytics-subtitle{color:var(--muted);font-size:.85rem;margin-top:4px}.analytics-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.analytics-filters{display:flex;align-items:end;gap:10px;flex-wrap:wrap}.analytics-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:12px;margin-bottom:16px}.analytics-kpi{background:#fff;border:1px solid var(--line);border-radius:13px;padding:16px;box-shadow:0 2px 8px #16324f08}.analytics-kpi .icon{color:var(--blue);font-size:1.1rem}.analytics-kpi small{display:block;color:var(--muted);margin-top:7px}.analytics-kpi strong{display:block;font-size:1.45rem;line-height:1.3;margin-top:5px}.analytics-kpi .compare{font-size:.76rem}.analytics-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.analytics-chart{height:285px;margin-top:14px}.analytics-table th{white-space:nowrap;color:#475467;font-size:.8rem}.analytics-thumb{width:70px;height:58px;object-fit:cover;border-radius:8px;background:#f2f4f7}.analytics-content{min-width:220px}.analytics-post{height:100%;border:1px solid var(--line);border-radius:12px;overflow:hidden}.analytics-post img{width:100%;height:150px;object-fit:cover;background:#f2f4f7}.analytics-post-body{padding:13px}.analytics-muted{color:var(--muted)}.analytics-empty{text-align:center;padding:28px!important;color:var(--muted)}@media(max-width:800px){.analytics-grid{grid-template-columns:1fr}}@media print{.sidebar,.topbar,.analytics-controls,.no-print{display:none!important}.main{padding:0!important}.analytics-panel,.analytics-kpi{break-inside:avoid;box-shadow:none}}
</style>
<div class="analytics-page">
<div class="analytics-panel analytics-controls"><form method="get" class="analytics-filters">
    <input type="hidden" name="project_id" value="{{ request('project_id') }}">
    <div><label class="form-label">Social account</label><select class="form-select" name="platform" onchange="this.form.submit()">@if($page->provider === 'facebook')<option value="facebook" @selected($platform==='facebook')>Facebook</option>@endif @if($page->provider === 'instagram' || $page->instagram_business_id)<option value="instagram" @selected($platform==='instagram')>Instagram</option>@endif</select></div>
    <div><label class="form-label">Page / account</label><select class="form-select" name="account_id" onchange="this.form.submit()">@foreach($accounts as $account)<option value="{{ $account->id }}" @selected($account->id===$page->id)>{{ $platform==='instagram' && ($account->instagram_username || $account->provider === 'instagram') ? '@'.($account->instagram_username ?: $account->page_name) : $account->page_name }}</option>@endforeach</select></div>
    <div><label class="form-label">Period</label><select class="form-select" name="range" id="range-filter"><option value="today" @selected($range==='today')>Today</option><option value="yesterday" @selected($range==='yesterday')>Yesterday</option><option value="last_7_days" @selected($range==='last_7_days')>Last 7 days</option><option value="last_30_days" @selected($range==='last_30_days')>Last 30 days</option><option value="this_month" @selected($range==='this_month')>This month</option><option value="last_month" @selected($range==='last_month')>Last month</option><option value="custom" @selected($range==='custom')>Custom range</option></select></div>
    <label class="small">From<input class="form-control" type="date" name="since" value="{{ $since }}"></label><label class="small">To<input class="form-control" type="date" name="until" value="{{ $until }}"></label><button class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
</form></div>
@if($error)
    <div class="alert alert-warning">
        <div>{{ $error }}</div>
        @if($platform === 'instagram' && $page->account?->status === 'reauthorization_required')
            <div class="mt-2">Meta rejected the Instagram token. Reconnect this Instagram account to issue a fresh token.</div>
            <a class="btn btn-sm btn-danger mt-2" href="{{ route('instagram.redirect', ['project_id' => $page->account->project_id]) }}"><i class="bi bi-instagram"></i> Reconnect Instagram</a>
        @elseif($platform !== 'instagram')
            <div class="mt-2">Check the connected account token and Meta permissions, then refresh.</div>
        @else
            <div class="mt-2">Check the Instagram API error and the permissions granted to this app.</div>
        @endif
    </div>
@endif
@if($platform === 'instagram' && !empty($data['media_unavailable']))
    <div class="alert alert-info">Instagram profile and account insights loaded. Meta did not return post details, so post counts and post likes are unavailable for this period.</div>
@endif
@if($data)
<div class="analytics-toolbar mb-3"><div><h1 class="h4 mb-1">{{ $platform==='instagram' ? 'Instagram' : 'Facebook' }} performance</h1><div class="analytics-subtitle">{{ $since }} – {{ $until }} · Synced {{ now()->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('M d, Y h:i A') }}</div></div></div>
@if($data['insights_window_limited'] ?? false)<div class="alert alert-info">Meta provides Instagram account insights from {{ $data['insights_since'] }} through {{ $data['insights_until'] }}. Published content below covers the full selected period.</div>@endif
<h2 class="analytics-title mb-2">Account overview</h2><div class="analytics-kpis">@foreach($kpis as [$label,$value,$icon])<div class="analytics-kpi"><span class="icon"><i class="bi {{ $icon }}"></i></span><small>{{ $label }}</small><strong>{{ is_numeric($value) ? (str_contains($label,'rate') ? number_format($value,2).'%' : number_format($value)) : 'N/A' }}</strong>@if($label==='Net follower change')@if($followerBreakdown)<small class="compare">{{ $followerBreakdown }}</small>@endif<small class="compare">{{ is_numeric($growthChange) ? (($growthChange>=0?'↑ ':'↓ ').number_format(abs($growthChange),1).'% vs previous period') : 'Net growth unavailable (Meta follow/unfollow data incomplete)' }}</small>@elseif($label==='Followers')<small class="compare">Current lifetime follower count</small>@elseif($previous && isset($previousTotals[$label]) && is_numeric($value) && is_numeric($previousTotals[$label]))@php($change=$previousTotals[$label]>0?($value-$previousTotals[$label])/$previousTotals[$label]*100:null)<small class="compare">{{ $change!==null ? (($change>=0?'↑ ':'↓ ').number_format(abs($change),1).'% vs previous period') : 'Previous period: '.number_format($previousTotals[$label]) }}</small>@endif</div>@endforeach</div>
<div class="analytics-grid">
<section class="analytics-panel"><h2 class="analytics-title">Daily views</h2><div class="analytics-chart"><canvas id="viewsChart"></canvas></div></section>
<section class="analytics-panel"><h2 class="analytics-title">Daily {{ strtolower($reachLabel) }}</h2>@if($reachAvailable)<div class="analytics-chart"><canvas id="reachChart"></canvas></div>@else<div class="analytics-empty">{{ $platform==='facebook' ? 'Meta did not return unique viewer data for this Page and date range.' : 'Meta did not return daily reach for this account and date range.' }}</div>@endif @if($platform==='facebook')<div class="analytics-subtitle">Unique viewers is Meta’s current reach-related Page metric; it is not the retired Reach metric.</div>@endif</section>
<section class="analytics-panel"><h2 class="analytics-title">Follower growth</h2><div class="analytics-chart"><canvas id="followersChart"></canvas></div><div class="analytics-subtitle">Daily new followers where Meta provides daily follow counts. Lifetime followers shown above.</div></section>
<section class="analytics-panel"><h2 class="analytics-title">Likes and comments</h2><div class="analytics-chart"><canvas id="engagementChart"></canvas></div><div class="analytics-subtitle">Bars group posts by the date they were published. Likes and comments are each post's current totals, not interactions received on that date.</div></section>
</div>
<section class="analytics-panel"><div class="analytics-toolbar"><div><h2 class="analytics-title">Daily performance summary</h2><div class="analytics-subtitle">Account insight metrics by day; post interactions by publication date.</div></div></div><div class="table-responsive mt-3"><table class="table table-hover align-middle analytics-table"><thead><tr><th>Date</th><th>Followers*</th><th>New follows (gross)</th><th>Views</th><th>{{ $reachLabel }}</th><th>Likes</th><th>Comments</th><th>Shares</th><th>Posts published</th></tr></thead><tbody>@foreach($daily as $row)<tr><td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('M d, Y') }}</td>@foreach(['followers','new_followers','views','reach','likes','comments','shares','posts'] as $key)<td>{{ is_numeric($row[$key]) ? number_format($row[$key]) : '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div><div class="analytics-subtitle">*A daily lifetime follower snapshot is unavailable. Daily follows are gross; net new followers subtract daily unfollows when Meta returns both metrics. Instagram daily follower growth is unavailable for this metric set.</div></section>
<section class="analytics-panel"><div class="analytics-toolbar"><div><h2 class="analytics-title">Engagement overview</h2><div class="analytics-subtitle">Post interactions returned for content published during this period.</div></div></div><div class="analytics-kpis mt-3">@foreach($interactionTotals as $label=>$value)<div class="analytics-kpi"><small>{{ $label }}</small><strong>{{ is_numeric($value) ? number_format($value) : 'N/A' }}</strong></div>@endforeach<div class="analytics-kpi"><small>Total engagements</small><strong>{{ is_numeric($totalEngagements) ? number_format($totalEngagements) : 'N/A' }}</strong></div></div></section>
<section class="analytics-panel"><h2 class="analytics-title">Content distribution · {{ ucfirst($platform) }}</h2><div class="analytics-chart"><canvas id="contentChart"></canvas></div><div class="analytics-subtitle">{{ !empty($data['media_unavailable']) ? 'Post details unavailable from Instagram for this period.' : $media->count().' published items in the selected period.' }}</div></section>
@if($demographicsAvailable)<div class="analytics-grid"><section class="analytics-panel"><h2 class="analytics-title">Audience demographics</h2><div class="analytics-subtitle">Follower demographics returned by Meta for this Instagram account.</div>@if($genderDistribution->isNotEmpty())<div style="height:230px"><canvas id="genderChart"></canvas></div>@endif</section><section class="analytics-panel"><h2 class="analytics-title">Audience by age</h2>@if($ageDistribution->isNotEmpty())<div style="height:260px"><canvas id="ageChart"></canvas></div>@endif</section></div>@endif
<section class="analytics-panel"><div class="analytics-toolbar"><div><h2 class="analytics-title">Recent published content</h2><div class="analytics-subtitle">Latest {{ $latestPosts->count() }} posts in the selected period.</div></div><a class="btn btn-sm btn-outline-primary" href="#all-content">View all posts</a></div><div class="row g-3 mt-1">@forelse($latestPosts as $item)<div class="col-sm-6 col-xl-4"><article class="analytics-post">@if(!empty($item['thumbnail']))<img src="{{ $item['thumbnail'] }}" alt="Post thumbnail" loading="lazy">@endif<div class="analytics-post-body"><div class="d-flex justify-content-between gap-2"><strong>{{ $typeLabel($item['type'] ?? '') }}</strong><small class="analytics-muted">{{ !empty($item['published_at']) ? \Illuminate\Support\Carbon::parse($item['published_at'])->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('M d, Y H:i') : '—' }}</small></div><p class="mt-2 mb-2">{{ \Illuminate\Support\Str::limit($item['text'] ?? 'Published post', 150) }}</p><div class="small analytics-muted">Views {{ data_get($item,'metrics.views',data_get($item,'metrics.post_media_view','—')) }} · Likes {{ data_get($item,'metrics.likes',data_get($item,'metrics.reactions','—')) }} · Comments {{ data_get($item,'metrics.comments','—') }} · Shares {{ data_get($item,'metrics.shares','—') }} · Saves {{ data_get($item,'metrics.saved','—') }}</div>@if(!empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-link px-0 mt-2">Open original post ↗</a>@endif</div></article></div>@empty<div class="col-12 analytics-empty">No published content was returned for this period.</div>@endforelse</div></section>
<section class="analytics-panel" id="all-content"><div class="analytics-toolbar"><div><h2 class="analytics-title">All published content</h2><div class="analytics-subtitle">{{ $media->count() }} items · {{ $since }} to {{ $until }}</div></div><div class="d-flex gap-2 no-print"><input id="post-search" class="form-control form-control-sm" placeholder="Search content"><select id="type-filter" class="form-select form-select-sm"><option value="">All types</option>@foreach($contentTypes as $type=>$count)<option>{{ $type }}</option>@endforeach</select></div></div><div class="table-responsive mt-3"><table class="table align-middle analytics-table" id="posts-table"><thead><tr><th>Published</th><th>Content</th><th>Type</th><th>Views</th><th>Likes / reactions</th><th>Comments</th><th>Shares</th><th>Saves</th></tr></thead><tbody>@forelse($media as $item)@php($likes=$item['metrics']['likes']??$item['metrics']['reactions']??null)<tr data-kind="{{ $typeLabel($item['type']??'') }}"><td>{{ !empty($item['published_at']) ? \Illuminate\Support\Carbon::parse($item['published_at'])->timezone(auth()->user()->timezone ?: config('app.timezone'))->format('M d, Y H:i') : '—' }}</td><td class="analytics-content">@if(!empty($item['thumbnail']))<img class="analytics-thumb me-2" src="{{ $item['thumbnail'] }}" alt="" loading="lazy">@endif{{ \Illuminate\Support\Str::limit($item['text'] ?? 'Untitled post',90) }} @if(!empty($item['url']))<a href="{{ $item['url'] }}" target="_blank" rel="noopener">Open</a>@endif</td><td>{{ $typeLabel($item['type']??'') }}</td><td>{{ data_get($item,'metrics.views',data_get($item,'metrics.post_media_view','—')) }}</td><td>{{ is_numeric($likes)?number_format($likes):'—' }}</td><td>{{ data_get($item,'metrics.comments','—') }}</td><td>{{ data_get($item,'metrics.shares','—') }}</td><td>{{ data_get($item,'metrics.saved','—') }}</td></tr>@empty<tr><td colspan="8" class="analytics-empty">No posts found.</td></tr>@endforelse</tbody></table></div></section>
@if(!empty($data['insights_unavailable']))<div class="analytics-subtitle mb-3">Metrics unavailable from Meta for this account or permission set: {{ collect($data['insights_unavailable'])->map(fn($m)=>str($m)->replace('_',' ')->title())->join(', ') }}.</div>@endif
@elseif(!$error)<section class="analytics-panel analytics-empty">No analytics data returned for this period.</section>@endif
</div>
@endsection
@if($data)
@push('scripts')<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>
const daily=@json($daily->values()), content=@json($contentTypes), genders=@json($genderDistribution), ages=@json($ageDistribution), labels=daily.map(x=>x.date), colors=['#1877f2','#d62976','#18a999','#f59e0b','#8064a2','#36a2eb'];
function lineChart(id,label,key,color){const el=document.getElementById(id);if(!el)return;new Chart(el,{type:'line',data:{labels,datasets:[{label,data:daily.map(x=>x[key]),borderColor:color,backgroundColor:color+'20',fill:true,tension:.3,spanGaps:true}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{callbacks:{title:v=>new Date(v[0].label+'T00:00:00').toLocaleDateString()}}},scales:{y:{beginAtZero:true}}}})}
lineChart('viewsChart','Views','views','#1877f2');lineChart('reachChart',@json($reachLabel),'reach','#18a999');lineChart('followersChart','New follows (gross)','new_followers','#7c5ce7');
const engagement=document.getElementById('engagementChart');if(engagement)new Chart(engagement,{type:'bar',data:{labels,datasets:[{label:'Likes / reactions on posts',data:daily.map(x=>x.likes),backgroundColor:'#d62976',borderRadius:4},{label:'Comments on posts',data:daily.map(x=>x.comments),backgroundColor:'#1877f2',borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'top'},tooltip:{callbacks:{title:v=>new Date(v[0].label+'T00:00:00').toLocaleDateString()}}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
const contentCanvas=document.getElementById('contentChart');if(contentCanvas)new Chart(contentCanvas,{type:'doughnut',data:{labels:Object.keys(content),datasets:[{data:Object.values(content),backgroundColor:colors}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
const genderCanvas=document.getElementById('genderChart');if(genderCanvas)new Chart(genderCanvas,{type:'doughnut',data:{labels:Object.keys(genders),datasets:[{data:Object.values(genders),backgroundColor:colors}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
const ageCanvas=document.getElementById('ageChart');if(ageCanvas)new Chart(ageCanvas,{type:'bar',data:{labels:Object.keys(ages),datasets:[{label:'Audience',data:Object.values(ages),backgroundColor:'#8064a2',borderRadius:5}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}});
const searchBox=document.getElementById('post-search'),typeFilter=document.getElementById('type-filter');function filterPosts(){const q=(searchBox?.value||'').toLowerCase(),t=typeFilter?.value||'';document.querySelectorAll('#posts-table tbody tr[data-kind]').forEach(row=>row.hidden=!row.innerText.toLowerCase().includes(q)||(t&&row.dataset.kind!==t))}searchBox?.addEventListener('input',filterPosts);typeFilter?.addEventListener('change',filterPosts);
document.getElementById('range-filter')?.addEventListener('change',e=>{const custom=e.target.value==='custom';document.querySelectorAll('input[name="since"],input[name="until"]').forEach(input=>input.required=custom)});
</script>@endpush
@endif

