<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MovePostRequest;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\SocialPage;
use App\Repositories\ProjectRepository;
use App\Repositories\SocialAccountRepository;
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

    public function create(ProjectRepository $projects): View
    {
        $project = $projects->findForUser(request()->user(), (int) request('project_id')) ?? $projects->projectsFor(request()->user())->first();
        return view('posts.create', [
            'projects' => $projects->projectsFor(request()->user()),
            'project' => $project,
            'pages' => $project ? SocialPage::query()->whereHas('account', fn ($query) => $query->where('user_id', auth()->id())->where('project_id', $project->id)->where('status', 'active'))->orderBy('provider')->orderBy('page_name')->get() : collect(),
        ]);
    }

    public function pages(\Illuminate\Http\Request $request, \App\Repositories\SocialAccountRepository $accounts): JsonResponse
    {
        abort_unless(\App\Models\Project::whereKey($request->integer('project_id'))->where('user_id', $request->user()->id)->exists(), 404);
        return response()->json($accounts->activePagesForProject($request->user()->id, $request->integer('project_id'))->map(fn ($page) => ['id' => $page->id, 'provider' => $page->provider, 'name' => $page->page_name]));
    }

    public function store(StorePostRequest $request, PostService $service, SchedulerService $scheduler): RedirectResponse
    {
        $post = $service->create($request->user(), $request->validated());

        if ($request->input('action') === 'publish') {
            $scheduler->dispatch($post);
        }

        return redirect()->route('posts.index')->with('success', $request->input('action') === 'publish' ? 'Post queued for publishing.' : 'Post saved.');
    }

    public function move(MovePostRequest $request, Post $post, PostService $service): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        abort_if(in_array($post->status, [\App\Enums\PostStatus::Published, \App\Enums\PostStatus::Publishing], true), 422, 'Published or publishing posts cannot be edited.');

        return response()->json($service->move($post, $request->string('scheduled_at'), $request->string('timezone')));
    }

    public function destroy(Post $post): RedirectResponse
    {
        abort_unless($post->user_id === auth()->id(), 403);
        $post->delete();

        return back()->with('success', 'Post deleted.');
    }
}
