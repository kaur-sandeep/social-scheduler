<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialPage;
use App\Services\Social\MetaAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Throwable;

class AnalyticsController extends Controller
{
    public function index(Request $request, MetaAnalyticsService $analytics): View
    {
        $request->validate(['range' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,this_month,last_month'], 'platform' => ['nullable', 'in:all,facebook,instagram']]);
        $timezone = $request->user()->timezone ?: config('app.timezone');
        $now = now($timezone);
        $range = $request->string('range', 'last_7_days')->toString();
        [$since, $until] = match ($range) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'yesterday' => [$now->copy()->subDay()->toDateString(), $now->copy()->subDay()->toDateString()],
            'last_30_days' => [$now->copy()->subDays(29)->toDateString(), $now->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$now->copy()->subDays(6)->toDateString(), $now->toDateString()],
        };
        $platform = $request->string('platform', 'all')->toString();
        $pages = SocialPage::query()->with('account')->where('provider', 'facebook')->where('status', 'active')
            ->whereHas('account', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))
            ->orderBy('page_name')->get();
        $reports = [];
        foreach ($pages as $page) {
            foreach (['facebook', 'instagram'] as $channel) {
                if ($channel === 'instagram' && ! $page->instagram_business_id) continue;
                if ($platform !== 'all' && $platform !== $channel) continue;
                try {
                    $reports[] = ['page' => $page, 'platform' => $channel, 'data' => $analytics->dashboard($page, $channel, $since, $until), 'error' => null];
                } catch (Throwable $e) {
                    $reports[] = ['page' => $page, 'platform' => $channel, 'data' => null, 'error' => $e->getMessage()];
                }
            }
        }
        return view('analytics.index', compact('reports', 'range', 'since', 'until', 'platform'));
    }

    public function show(Request $request, SocialPage $page, string $platform, MetaAnalyticsService $analytics): View|RedirectResponse
    {
        abort_unless(in_array($platform, ['facebook', 'instagram'], true), 404);
        abort_unless($page->provider === 'facebook' && ($platform === 'facebook' || $page->instagram_business_id), 404);
        abort_unless($page->status === 'active' && $page->account()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->when($request->filled('project_id'), fn ($query) => $query->where('project_id', $request->integer('project_id')))
            ->exists(), 404);
        $timezone = $request->user()->timezone ?: config('app.timezone');
        $request->validate(['range' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,this_month,last_month,custom'], 'since' => ['nullable', 'date'], 'until' => ['nullable', 'date', 'after_or_equal:since'], 'q' => ['nullable', 'string', 'max:200'], 'type' => ['nullable', 'string', 'max:40']]);
        $range = $request->string('range', 'last_7_days')->toString();
        $now = now($timezone);
        [$since, $until] = match ($range) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'yesterday' => [$now->copy()->subDay()->toDateString(), $now->copy()->subDay()->toDateString()],
            'last_30_days' => [$now->copy()->subDays(29)->toDateString(), $now->toDateString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'custom' => [$request->input('since', $now->toDateString()), $request->input('until', $now->toDateString())],
            default => [$now->copy()->subDays(6)->toDateString(), $now->toDateString()],
        };
        abort_if(Carbon::parse($since)->diffInDays(Carbon::parse($until)) > 366, 422, 'Choose a date range of 367 days or less.');
        $projectId = $page->account->project_id;
        $accounts = SocialPage::query()->with('account')->where('provider', 'facebook')->where('status', 'active')
            ->whereHas('account', fn ($q) => $q->where('user_id', $request->user()->id)->where('project_id', $projectId)->where('status', 'active'))
            ->where(fn ($q) => $q->where('provider', 'facebook')->when($platform === 'instagram', fn ($q) => $q->whereNotNull('instagram_business_id')))
            ->orderBy('page_name')->get();
        if ($request->filled('account_id')) {
            $selected = $accounts->firstWhere('id', $request->integer('account_id'));
            abort_unless($selected, 404);
            if (! $selected->is($page)) {
                return redirect()->route('accounts.analytics', ['page' => $selected, 'platform' => $platform, 'project_id' => $projectId, 'range' => $range, 'since' => $since, 'until' => $until]);
            }
        }
        $days = Carbon::parse($since)->diffInDays(Carbon::parse($until)) + 1;
        $previousUntil = Carbon::parse($since)->subDay();
        $previousSince = $previousUntil->copy()->subDays($days - 1);
        $data = null; $previous = null; $error = null;
        try {
            $data = $analytics->dashboard($page, $platform, $since, $until);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        if ($data) {
            try {
                $previous = $analytics->dashboard($page, $platform, $previousSince->toDateString(), $previousUntil->toDateString());
            } catch (Throwable) {
                // Previous-period data is supplementary; the selected report remains usable.
            }
        }
        if ($request->boolean('csv') && $data) {
            $filename = strtolower($platform).'-analytics-'.$since.'-to-'.$until.'.csv';
            $data['media'] = collect($data['media'])->filter(function ($item) use ($request) {
                $matchesText = ! $request->filled('q') || str_contains(mb_strtolower($item['text'] ?? ''), mb_strtolower($request->string('q')->toString()));
                $matchesType = ! $request->filled('type') || ($item['type'] ?? '') === $request->string('type')->toString();
                return $matchesText && $matchesType;
            })->values()->all();
            return Response::streamDownload(function () use ($data, $page, $platform, $since, $until) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Platform', 'Account', 'Period', 'Post', 'Type', 'Published', 'Metric', 'Value']);
                foreach ($data['media'] as $item) {
                    $metrics = $item['metrics'] ?: ['unavailable' => 'Not available'];
                    foreach ($metrics as $metric => $value) {
                        $caption = (string) ($item['text'] ?? '');
                        if (preg_match('/^[=+@\-\t\r]/', $caption)) $caption = "'".$caption;
                        fputcsv($out, [ucfirst($platform), $page->page_name, "$since to $until", $caption, $item['type'], $item['published_at'], $metric, $value]);
                    }
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        return view('analytics.professional', compact('page', 'platform', 'range', 'since', 'until', 'data', 'previous', 'previousSince', 'previousUntil', 'error', 'accounts'));
    }
}
