<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Repositories\ProjectRepository;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(ProjectRepository $projects): View
    {
        $project = $projects->findForUser(request()->user(), (int) request('project_id')) ?? $projects->projectsFor(request()->user())->first();
        return view('accounts.index', [
            'projects' => $projects->projectsFor(request()->user()), 'project' => $project,
            'accounts' => SocialAccount::with('pages')->where('user_id', auth()->id())->when($project, fn ($q) => $q->where('project_id', $project->id))->latest()->get(),
        ]);
    }
}
