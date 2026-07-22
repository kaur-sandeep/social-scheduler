<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\SocialProvider;
use App\Repositories\PostRepository;
use App\Repositories\ProjectRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(ProjectRepository $projects): View
    {
        return view('calendar.index', ['projects' => $projects->projectsFor(request()->user())]);
    }

    public function events(Request $request, PostRepository $posts): JsonResponse
    {
        $events = $posts->calendarEvents($request->user()->id, $request->only(['start', 'end', 'platform', 'project', 'status']))
            ->map(function ($post) {
                $media = $post->media->first();

                return [
                    'id' => $post->id,
                    'title' => ucfirst($post->platform).' - '.str($post->message)->limit(42),
                    'start' => optional($post->scheduled_at)->toIso8601String(),
                    'display' => 'block',
                    'backgroundColor' => match ($post->status->value) {
                        'draft' => '#ffedd5',
                        'pending', 'queued', 'retrying' => '#fee2e2',
                        'published' => '#dcfae6',
                        'failed', 'cancelled' => '#fef3f2',
                        'publishing' => '#e0f2fe',
                        default => SocialProvider::tryFrom($post->platform)?->color() ?? '#64748B',
                    },
                    'textColor' => match ($post->status->value) {
                        'draft' => '#c2410c',
                        'pending', 'queued', 'retrying', 'failed', 'cancelled' => '#b42318',
                        'published' => '#087443',
                        'publishing' => '#0369a1',
                        default => '#ffffff',
                    },
                    'borderColor' => 'transparent',
                    'extendedProps' => [
                        'platform' => $post->platform,
                        'status' => $post->status->value,
                        'page' => $post->socialPage?->page_name,
                        'project' => $post->project?->name,
                        'caption' => $post->message,
                        'thumbnail' => $media ? asset('storage/'.$media->thumbnail_path) : null,
                    ],
                ];
            });

        return response()->json($events);
    }
}
