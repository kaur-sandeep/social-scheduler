<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily Social Media Performance Report</title></head>
<body style="margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif;color:#17243a">
<div style="width:100%;margin:0;padding:0">
    <main style="background:transparent;padding:0;border:0;border-radius:0">
        <p style="margin:0 0 18px;color:#35465e;line-height:1.55">Hello Team,<br>Please find the social media report below.</p>
        @php($hiddenColumnKeys = ['yesterday_unique_views', 'seven_day_unique_views', 'yesterday_likes', 'seven_day_likes', 'yesterday_shares', 'seven_day_shares', 'yesterday_saves', 'seven_day_saves'])
        @php($visibleColumnCount = count($report['columns']) - count($hiddenColumnKeys))
        @foreach($report['table_groups'] as $tableGroup)
            @if(!empty($tableGroup['rows']))
                <h3 style="font-size:15px;margin:18px 0 6px">{{ $tableGroup['brand'] }}</h3>
                <div style="overflow-x:auto;border:1px solid #dce4f0;border-radius:8px;margin-bottom:12px">
                    <table style="border-collapse:collapse;width:100%;min-width:900px;font-size:12px">
                        <thead><tr>@foreach($report['columns'] as $index => $column)@if(!in_array($report['column_keys'][$index], $hiddenColumnKeys, true))<th style="background:#edf3fb;text-align:left;padding:9px;border-bottom:1px solid #dce4f0;white-space:nowrap">{{ $column }}</th>@endif @endforeach</tr></thead>
                        <tbody>@foreach($tableGroup['rows'] as $row)<tr>@foreach($report['column_keys'] as $key)@if(!in_array($key, $hiddenColumnKeys, true))<td style="padding:9px;border-bottom:1px solid #edf1f7;white-space:nowrap;{{ $key === 'yesterday_net_change' && is_numeric($row[$key] ?? null) ? 'color:'.($row[$key] >= 0 ? '#15803d' : '#b42318').';font-weight:bold;' : '' }}">@php($value=$row[$key]??null){{ is_array($value) ? (count($value) ? collect($value)->map(fn($count,$type)=>$type.': '.$count)->join(', ') : '0') : (\App\Support\ReportMetric::isNumericField($key) ? \App\Support\ReportMetric::display($value) : ($value ?? '')) }}</td>@endif @endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
            @endif
        @endforeach
        @if(collect($report['table_groups'])->every(fn ($group) => empty($group['rows'])))
            <p style="padding:14px;text-align:center;color:#68778b">No active Facebook, Instagram, or TikTok accounts were found.</p>
        @endif
        @if(!empty($report['comment_report_rows']))
        <h2 style="font-size:18px;margin:24px 0 8px">Comments report summary</h2>
        <p style="margin:0 0 12px;color:#68778b;font-size:12px">Comments totals are grouped by report date. {{ $report['comment_day_label'] }}: {{ $report['comment_day_date'] }} &nbsp;|&nbsp; Last 7 days: {{ $report['comment_seven_day_label'] }}</p>
        <div style="overflow-x:auto;border:1px solid #dce4f0;border-radius:8px;margin-bottom:12px">
            <table style="border-collapse:collapse;width:100%;min-width:600px;font-size:12px">
                <thead><tr>
                    <th style="background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Project Name</th>
                    <th style="background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Account Type</th>
                    <th style="background:#edf3fb;text-align:right;padding:10px;border-bottom:1px solid #dce4f0">{{ $report['comment_day_label'] }} Comments Count</th>
                    <th style="background:#edf3fb;text-align:right;padding:10px;border-bottom:1px solid #dce4f0">Last 7 Days Comments Count</th>
                </tr></thead>
                <tbody>
                    @foreach($report['comment_report_rows'] as $commentRow)
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #edf1f7">{{ $commentRow['project_name'] }}</td>
                            <td style="padding:10px;border-bottom:1px solid #edf1f7">{{ $commentRow['account_type'] }}</td>
                            <td style="padding:10px;text-align:right;border-bottom:1px solid #edf1f7">{{ number_format($commentRow['day_comments']) }}</td>
                            <td style="padding:10px;text-align:right;border-bottom:1px solid #edf1f7">{{ number_format($commentRow['seven_day_comments']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @if(!empty($report['comment_detail_rows']))
        <h3 style="font-size:15px;margin:18px 0 8px">Comments details and post URLs</h3>
        <div style="overflow-x:auto;border:1px solid #dce4f0;border-radius:8px;margin-bottom:12px">
            <table style="border-collapse:collapse;width:100%;min-width:800px;font-size:12px;table-layout:fixed">
                <thead><tr>
                    <th style="width:105px;background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Report Date</th>
                    <th style="width:140px;background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Project Name</th>
                    <th style="width:100px;background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Account Type</th>
                    <th style="background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Comment</th>
                    <th style="width:190px;background:#edf3fb;text-align:left;padding:10px;border-bottom:1px solid #dce4f0">Post URL</th>
                </tr></thead>
                <tbody>
                    @foreach($report['comment_detail_rows'] as $detailRow)
                        <tr>
                            <td style="vertical-align:top;padding:10px;border-bottom:1px solid #edf1f7">{{ $detailRow['report_date'] }}</td>
                            <td style="vertical-align:top;padding:10px;border-bottom:1px solid #edf1f7;overflow-wrap:anywhere">{{ $detailRow['project_name'] }}</td>
                            <td style="vertical-align:top;padding:10px;border-bottom:1px solid #edf1f7">{{ $detailRow['account_type'] }}</td>
                            <td style="vertical-align:top;padding:10px;border-bottom:1px solid #edf1f7;white-space:pre-wrap;overflow-wrap:anywhere">{{ $detailRow['comment_text'] }}</td>
                            <td style="vertical-align:top;padding:10px;border-bottom:1px solid #edf1f7;overflow-wrap:anywhere"><a href="{{ $detailRow['post_url'] }}" style="color:#1769aa">View post</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        <h2 style="font-size:18px;margin:24px 0 8px">Performance summary</h2>
        <p style="margin:0 0 12px;color:#68778b;font-size:12px">Current period: {{ $report['current_period']['label'] }} &nbsp;|&nbsp; Previous period: {{ $report['previous_period']['label'] }}</p>
        @foreach($report['brand_summaries'] as $brandSummary)
            <h3 style="font-size:15px;margin:16px 0 5px">{{ $brandSummary['brand'] }}</h3>
            @php($platformGroups = collect($brandSummary['accounts'])->map(fn ($account) => ['platform' => $account['account_type'] ?? 'Other', 'points' => $account['comparison']['points'] ?? []])->filter(fn ($account) => !empty($account['points']))->groupBy('platform'))
            @foreach($platformGroups as $platform => $accounts)
                <h4 style="font-size:13px;margin:10px 0 3px">{{ $platform }}</h4>
                <ul style="margin:0;padding-left:22px;color:#35465e;line-height:1.6">
                    @foreach($accounts as $account)
                        @foreach($account['points'] as $point)
                            <li style="padding:1px 0">{{ $point }}</li>
                        @endforeach
                    @endforeach
                </ul>
            @endforeach
        @endforeach
        <p style="margin:20px 0 0;color:#35465e;line-height:1.55">Regards,<br>Team Cogniter</p>
    </main>
</div>
</body>
</html>
