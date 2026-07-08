<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $userId = auth()->id();

        return view('dashboard.index', [
            'metrics' => [
                'today' => Post::where('user_id', $userId)->whereDate('scheduled_at', today())->count(),
                'week' => Post::where('user_id', $userId)->whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'month' => Post::where('user_id', $userId)->whereMonth('scheduled_at', now()->month)->whereYear('scheduled_at', now()->year)->count(),
                'published' => Post::where('user_id', $userId)->where('status', PostStatus::Published)->count(),
                'failed' => Post::where('user_id', $userId)->where('status', PostStatus::Failed)->count(),
                'pending' => Post::where('user_id', $userId)->where('status', PostStatus::Pending)->count(),
                'drafts' => Post::where('user_id', $userId)->where('status', PostStatus::Draft)->count(),
            ],
            'upcoming' => Post::with('socialPage')->where('user_id', $userId)->where('scheduled_at', '>=', now())->orderBy('scheduled_at')->limit(10)->get(),
            'recent' => Post::with('socialPage')->where('user_id', $userId)->latest()->limit(10)->get(),
        ]);
    }
}
