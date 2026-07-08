<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovePostRequest;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\SocialPage;
use App\Repositories\PostRepository;
use App\Services\PostService;
use App\Services\SchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(PostRepository $posts): View
    {
        return view('posts.index', [
            'posts' => $posts->paginateForUser(auth()->id(), request()->only(['platform', 'status'])),
        ]);
    }

    public function create(): View
    {
        return view('posts.create', [
            'pages' => SocialPage::query()
                ->whereHas('account', fn ($query) => $query->where('user_id', auth()->id())->where('status', 'active'))
                ->orderBy('provider')
                ->orderBy('page_name')
                ->get(),
        ]);
    }

    public function store(StorePostRequest $request, PostService $service, SchedulerService $scheduler): RedirectResponse
    {
        $post = $service->create($request->user(), $request->validated());

        if ($request->input('publish_now') === '1') {
            $scheduler->dispatch($post);
        }

        return redirect()->route('posts.index')->with('success', 'Post saved.');
    }

    public function move(MovePostRequest $request, Post $post, PostService $service): JsonResponse
    {
        return response()->json($service->move($post, $request->string('scheduled_at'), $request->string('timezone')));
    }

    public function destroy(Post $post): RedirectResponse
    {
        abort_unless($post->user_id === auth()->id(), 403);
        $post->delete();

        return back()->with('success', 'Post deleted.');
    }
}
