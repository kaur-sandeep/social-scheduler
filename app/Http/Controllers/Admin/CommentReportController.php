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
    private const ACCOUNT_TYPES = ['Facebook', 'Instagram', 'TikTok'];
    private const PROJECT_NAMES = ['Belize On Top', 'Wheels Belize', 'Tourist Point'];

    public function index(Request $request): View
    {
        $reportQuery = CommentReport::where('user_id', $request->user()->id);
        $reportCount = (clone $reportQuery)->count();

        $reports = $reportQuery->with('posts')
            ->latest('report_date')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('comment-reports.index', [
            'reports' => $reports,
            'reportCount' => $reportCount,
            'accountTypes' => self::ACCOUNT_TYPES,
            'projectNames' => self::PROJECT_NAMES,
        ]);
    }

    public function edit(Request $request, int $commentReport): View
    {
        $report = CommentReport::where('user_id', $request->user()->id)
            ->with('posts')
            ->findOrFail($commentReport);

        return view('comment-reports.edit', [
            'report' => $report,
            'accountTypes' => self::ACCOUNT_TYPES,
            'projectNames' => self::PROJECT_NAMES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_name' => ['required', 'string', Rule::in(self::PROJECT_NAMES)],
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

    public function update(Request $request, int $commentReport): RedirectResponse
    {
        $report = CommentReport::where('user_id', $request->user()->id)->findOrFail($commentReport);
        $validated = $request->validate([
            'project_name' => ['required', 'string', Rule::in(self::PROJECT_NAMES)],
            'account_type' => ['required', 'string', Rule::in(self::ACCOUNT_TYPES)],
            'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'number_of_comments' => ['required', 'integer', 'min:1', 'max:100'],
            'comments' => ['required', 'array', 'size:'.$request->input('number_of_comments')],
            'comments.*' => ['required', 'string', 'max:10000'],
            'post_urls' => ['required', 'array', 'size:'.$request->input('number_of_comments')],
            'post_urls.*' => ['required', 'url', 'max:2048'],
        ]);

        DB::transaction(function () use ($report, $validated): void {
            $report->update([
                'project_name' => $validated['project_name'],
                'account_type' => $validated['account_type'],
                'report_date' => $validated['report_date'],
                'number_of_comments' => $validated['number_of_comments'],
            ]);
            $report->posts()->delete();
            foreach ($validated['post_urls'] as $index => $url) {
                $report->posts()->create([
                    'position' => $index + 1,
                    'comment_text' => $validated['comments'][$index],
                    'post_url' => $url,
                ]);
            }
        });

        return redirect()->route('comment-reports.index')->with('success', 'Comment report updated successfully.');
    }

    public function destroy(Request $request, int $commentReport): RedirectResponse
    {
        $report = CommentReport::where('user_id', $request->user()->id)->findOrFail($commentReport);
        $report->delete();

        return redirect()->route('comment-reports.index')->with('success', 'Comment report deleted successfully.');
    }
}
