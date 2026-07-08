<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\SocialProvider;
use App\Repositories\PostRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(): View
    {
        return view('calendar.index');
    }

    public function events(Request $request, PostRepository $posts): JsonResponse
    {
        $events = $posts->calendarEvents($request->user()->id, $request->only(['start', 'end', 'platform', 'status']))
            ->map(function ($post) {
                $media = $post->media->first();

                return [
                    'id' => $post->id,
                    'title' => ucfirst($post->platform).' - '.str($post->message)->limit(42),
                    'start' => optional($post->scheduled_at)->toIso8601String(),
                    'backgroundColor' => SocialProvider::tryFrom($post->platform)?->color() ?? '#64748B',
                    'borderColor' => SocialProvider::tryFrom($post->platform)?->color() ?? '#64748B',
                    'extendedProps' => [
                        'platform' => $post->platform,
                        'status' => $post->status->value,
                        'page' => $post->socialPage?->page_name,
                        'caption' => $post->message,
                        'thumbnail' => $media ? asset('storage/'.$media->thumbnail_path) : null,
                    ],
                ];
            });

        return response()->json($events);
    }
}
