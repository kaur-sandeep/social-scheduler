<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectCredentialService;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    protected function selectOAuthProject(\Illuminate\Http\Request $request, string $provider, ProjectCredentialService $credentials): ?RedirectResponse
    {
        $project = Project::whereKey($request->integer('project_id'))->where('user_id', $request->user()->id)->first();
        if (! $project) return redirect()->route('accounts.index')->with('error', 'Select a project before connecting an account.');
        try { $credentials->forProject($project, $provider); } catch (\RuntimeException $exception) { return redirect()->route('accounts.index', ['project_id' => $project->id])->with('error', $exception->getMessage()); }
        session(['oauth_project_id' => $project->id]);
        return null;
    }
}
