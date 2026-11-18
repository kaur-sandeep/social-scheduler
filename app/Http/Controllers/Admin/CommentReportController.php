<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommentReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommentReportController extends Controller
{
    private const ACCOUNT_TYPES = ['Facebook', 'Instagram', 'TikTok', 'YouTube', 'LinkedIn', 'X (Twitter)', 'Pinterest'];

    public function index(Request $request): View
    {
        $reportQuery = CommentReport::where('user_id', $request->user()->id);
        $reportCount = (clone $reportQuery)->count();

        $reports = $reportQuery->with('posts')
            ->latest('report_date')
            ->latest()
            ->paginate(10);

        return view('comment-reports.index', ['reports' => $reports, 'reportCount' => $reportCount, 'accountTypes' => self::ACCOUNT_TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'string', Rule::in(self::ACCOUNT_TYPES)],
            'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'number_of_comments' => ['required', 'integer', 'min:1', 'max:100'],
            'comments' => ['required', 'array', 'size:'.$request->input('number_of_comments')],
            'comments.*' => ['required', 'string', 'max:10000'],
            'post_urls' => ['required', 'array', 'size:'.$request->input('number_of_comments')],
            'post_urls.*' => ['required', 'url', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $report = CommentReport::create([
                'user_id' => $request->user()->id,
                'project_name' => $validated['project_name'],
                'account_type' => $validated['account_type'],
                'report_date' => $validated['report_date'],
                'number_of_comments' => $validated['number_of_comments'],
            ]);

            foreach ($validated['post_urls'] as $index => $url) {
                $report->posts()->create([
                    'position' => $index + 1,
                    'comment_text' => $validated['comments'][$index],
                    'post_url' => $url,
                ]);
            }
        });

        return redirect()->route('comment-reports.index')->with('success', 'Comment report saved successfully.');
    }
}
