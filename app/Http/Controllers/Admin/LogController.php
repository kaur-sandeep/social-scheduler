<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostLog;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(): View
    {
        return view('logs.index', [
            'logs' => PostLog::with('post.socialPage')
                ->whereHas('post', fn ($query) => $query->where('user_id', auth()->id()))
                ->latest()
                ->paginate(25),
        ]);
    }
}
