<?php
namespace App\Services;
use App\Models\Project;
use App\Models\SocialAppCredential;
use RuntimeException;
class ProjectCredentialService {
    public function forProject(Project $project, string $provider): SocialAppCredential { $credential = $project->socialAppCredentials()->where('provider', $provider)->first(); if (! $credential || ! $credential->isUsable()) throw new RuntimeException('Please configure your '.ucfirst($provider).' App credentials in Project Settings before connecting your account.'); return $credential; }
    public function fromSession(string $provider): SocialAppCredential { $project = Project::find(session('oauth_project_id')); if (! $project) throw new RuntimeException('Select a project before connecting an account.'); return $this->forProject($project, $provider); }
}
