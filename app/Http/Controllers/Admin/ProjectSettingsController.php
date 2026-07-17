<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProjectCredentialsRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;
use Illuminate\View\View;

class ProjectSettingsController extends Controller
{
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::create(['user_id' => $request->user()->id, 'name' => $request->string('name')->trim()]);
        ActivityLog::create(['user_id' => $request->user()->id, 'event' => 'project_created', 'subject_type' => Project::class, 'subject_id' => $project->id]);
        return redirect()->route('project-settings.index', ['project_id' => $project->id])->with('success', 'Project created. Configure its social app credentials below.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        abort_unless($project->user_id === request()->user()->id, 403);
        $project->delete();
        ActivityLog::create(['user_id' => request()->user()->id, 'event' => 'project_deleted', 'subject_type' => Project::class, 'subject_id' => $project->id]);
        return redirect()->route('project-settings.index')->with('success', 'Project deleted.');
    }

    public function index(ProjectRepository $projects): View
    {
        $project = $projects->findForUser(request()->user(), (int) request('project_id')) ?? $projects->projectsFor(request()->user())->first();
        abort_unless($project, 404, 'Create a project before configuring credentials.');
        return view('project-settings.index', ['project' => $project, 'projects' => $projects->projectsFor(request()->user()), 'credentials' => $project->socialAppCredentials->keyBy('provider')]);
    }
    public function update(UpdateProjectCredentialsRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            foreach ($request->validated('credentials') as $provider => $values) {
                if (blank($values['client_id'] ?? null) && blank($values['client_secret'] ?? null)) { continue; }
                $credential = $project->socialAppCredentials()->firstOrNew(['provider' => $provider]);
                $credential->fill(['client_id' => $values['client_id'], 'redirect_uri' => $values['redirect_uri'] ?? null, 'status' => $values['status']]);
                if (filled($values['client_secret'] ?? null)) $credential->client_secret = $values['client_secret'];
                $credential->save();
            }
        });
        ActivityLog::create(['user_id' => $request->user()->id, 'event' => 'project_credentials_updated', 'subject_type' => Project::class, 'subject_id' => $project->id]);
        return back()->with('success', 'Project credentials saved.');
    }
}
