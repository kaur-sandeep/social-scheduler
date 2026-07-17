<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\SocialAppCredential;
use App\Models\User;

class ProjectRepository
{
    public function projectsFor(User $user) { return Project::where('user_id', $user->id)->orderBy('name')->get(); }
    public function findForUser(User $user, int $id): ?Project { return Project::where('user_id', $user->id)->find($id); }
    public function credential(Project $project, string $provider): ?SocialAppCredential { return $project->socialAppCredentials()->where('provider', $provider)->first(); }
    public function deletedFor(User $user) { return Project::onlyTrashed()->where('user_id', $user->id)->latest('deleted_at')->get(); }
}
