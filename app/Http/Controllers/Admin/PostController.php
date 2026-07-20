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
    public function index(PostRepository $posts): View|JsonResponse
    {
        $posts = $posts->paginateForUser(auth()->id(), request()->only(['platform', 'status', 'q']));

        if (request()->expectsJson()) {
            return response()->json(['html' => view('posts.partials.results', compact('posts'))->render()]);
        }

        return view('posts.index', compact('posts'));
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
        return response()->json($accounts->activePagesForProject($request->user()->id, $request->integer('project_id'))->map(fn ($page) => ['id' => $page->id, 'provider' => $page->provider, 'name' => $page->page_name, 'instagram_username' => $page->instagram_username, 'instagram_business_id' => $page->instagram_business_id]));
    }

    public function store(StorePostRequest $request, PostService $service, SchedulerService $scheduler): RedirectResponse
    {
        $post = $service->create($request->user(), $request->validated());

        if ($request->input('action') === 'publish') {
            $scheduler->dispatch($post);
        }

        return redirect()->route('posts.index')->with('success', $request->input('action') === 'publish' ? 'Post queued for publishing.' : 'Post saved.');
    }

    public function edit(Post $post, ProjectRepository $projects): View
    {
        abort_unless($post->user_id === request()->user()->id, 403);
        abort_if(in_array($post->status, [\App\Enums\PostStatus::Published, \App\Enums\PostStatus::Publishing], true), 422, 'Published or publishing posts cannot be edited.');

        $project = $post->project ?? $projects->projectsFor(request()->user())->first();

        return view('posts.create', [
            'projects' => $projects->projectsFor(request()->user()),
            'project' => $project,
            'post' => $post->load('media'),
            'pages' => $project ? SocialPage::query()->whereHas('account', fn ($query) => $query->where('user_id', auth()->id())->where('project_id', $project->id)->where('status', 'active'))->orderBy('provider')->orderBy('page_name')->get() : collect(),
        ]);
    }

    public function update(StorePostRequest $request, Post $post, PostService $service, SchedulerService $scheduler): RedirectResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        abort_if(in_array($post->status, [\App\Enums\PostStatus::Published, \App\Enums\PostStatus::Publishing], true), 422, 'Published or publishing posts cannot be edited.');

        $post = $service->update($post, $request->validated());
        if ($request->input('action') === 'publish') $scheduler->dispatch($post);

        return redirect()->route('posts.index')->with('success', 'Post updated.');
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

        return back()->with('success', 'Post moved to deleted records. You can restore it later.');
    }

    public function deleted(PostRepository $posts): View
    {
        return view('posts.deleted', ['posts' => $posts->deletedForUser(auth()->id())]);
    }

    public function restore(int $post): RedirectResponse
    {
        $post = Post::onlyTrashed()->where('user_id', auth()->id())->findOrFail($post);
        $post->restore();
        return back()->with('success', 'Post restored.');
    }

    public function forceDestroy(int $post): RedirectResponse
    {
        abort_unless(auth()->user()->is_admin, 403);
        $post = Post::onlyTrashed()->where('user_id', auth()->id())->findOrFail($post);
        $post->forceDelete();

        return back()->with('success', 'Post permanently deleted.');
    }
}
