<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily Social Media Performance Report</title></head>
<body style="margin:0;background:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#17243a">
<div style="max-width:1200px;margin:0 auto;padding:24px 12px">
    <header style="background:#142447;color:#fff;padding:24px;border-radius:12px 12px 0 0">
        <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:#b9d8ff">{{ config('app.name') }}</div>
        <h1 style="font-size:24px;margin:8px 0">Daily Social Media Performance</h1>
        <div style="font-size:14px;color:#d8e5f6">Report generated {{ $report['generated_at'] }} · Reporting period: {{ $report['period_label'] }}</div>
    </header>
    <main style="background:#fff;padding:22px;border:1px solid #dce4f0;border-top:0;border-radius:0 0 12px 12px">
        <h2 style="font-size:18px;margin:4px 0 10px">Project and account performance</h2>
        <div style="overflow-x:auto;border:1px solid #dce4f0;border-radius:8px">
            <table style="border-collapse:collapse;width:100%;min-width:1500px;font-size:12px">
                <thead><tr>@foreach($report['columns'] as $column)<th style="background:#edf3fb;text-align:left;padding:9px;border-bottom:1px solid #dce4f0;white-space:nowrap">{{ $column }}</th>@endforeach</tr></thead>
                <tbody>@forelse($report['rows'] as $row)<tr>@foreach($report['column_keys'] as $key)<td style="padding:9px;border-bottom:1px solid #edf1f7;white-space:nowrap;{{ $key === 'yesterday_net_change' && is_numeric($row[$key] ?? null) ? 'color:'.($row[$key] >= 0 ? '#15803d' : '#b42318').';font-weight:bold;' : '' }}">@php($value=$row[$key]??null){{ is_array($value) ? (count($value) ? collect($value)->map(fn($count,$type)=>$type.': '.$count)->join(', ') : '0') : (\App\Support\ReportMetric::isNumericField($key) ? \App\Support\ReportMetric::display($value) : ($value ?? '')) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['columns']) }}" style="padding:14px;text-align:center;color:#68778b">No active Facebook or Instagram accounts were found.</td></tr>@endforelse</tbody>
            </table>
        </div>

        <h2 style="font-size:18px;margin:24px 0 8px">Performance highlights</h2>
        @if($report['highlights'])<ul style="margin:0;padding-left:20px;color:#35465e">@foreach($report['highlights'] as $highlight)<li style="margin:7px 0">{{ $highlight }}</li>@endforeach</ul>@else<p style="color:#68778b">No highlights could be calculated from the available metrics.</p>@endif
        <p style="font-size:12px;color:#68778b;margin-top:24px">A zero means no value was reported for that numeric metric. Instagram and Facebook unique audience values retain their platform definitions. Seven-day unique viewers are not summed from daily unique counts. Period likes, comments, shares and saves are shown as zero when a reliable value is unavailable. All published post counts use successful Social Scheduler publishing records.</p>
        <p style="font-size:12px;color:#68778b">CSV report is attached for spreadsheet review.</p>
    </main>
</div>
</body>
</html>
