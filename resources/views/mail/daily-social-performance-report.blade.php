<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily Social Media Performance Report</title></head>
<body style="margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#17243a">
<div style="width:100%;margin:0;padding:0">
    <main style="background:transparent;padding:0;border:0;border-radius:0">
        <p style="margin:0 0 18px;color:#35465e;line-height:1.55">Hello Team,<br>Please check the social media report below.</p>
        <h2 style="font-size:18px;margin:4px 0 10px">Project and account performance</h2>
        <div style="overflow-x:auto;border:1px solid #dce4f0;border-radius:8px">
            <table style="border-collapse:collapse;width:100%;min-width:1500px;font-size:12px">
                <thead><tr>@foreach($report['columns'] as $column)<th style="background:#edf3fb;text-align:left;padding:9px;border-bottom:1px solid #dce4f0;white-space:nowrap">{{ $column }}</th>@endforeach</tr></thead>
                <tbody>@forelse($report['rows'] as $row)<tr>@foreach($report['column_keys'] as $key)<td style="padding:9px;border-bottom:1px solid #edf1f7;white-space:nowrap;{{ $key === 'yesterday_net_change' && is_numeric($row[$key] ?? null) ? 'color:'.($row[$key] >= 0 ? '#15803d' : '#b42318').';font-weight:bold;' : '' }}">@php($value=$row[$key]??null){{ is_array($value) ? (count($value) ? collect($value)->map(fn($count,$type)=>$type.': '.$count)->join(', ') : '0') : (\App\Support\ReportMetric::isNumericField($key) ? \App\Support\ReportMetric::display($value) : ($value ?? '')) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['columns']) }}" style="padding:14px;text-align:center;color:#68778b">No active Facebook or Instagram accounts were found.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <h2 style="font-size:18px;margin:24px 0 8px">Performance summary</h2>
        @foreach($report['brand_summaries'] as $brandSummary)
            <h3 style="font-size:15px;margin:16px 0 5px">{{ $brandSummary['brand'] }}</h3>
            <p style="margin:0 0 12px;color:#35465e;line-height:1.55">{{ $brandSummary['summary'] }}</p>
        @endforeach
        <p style="margin:20px 0 0;color:#35465e;line-height:1.55">Regards,<br>Team Cogniter</p>
    </main>
</div>
</body>
</html>
