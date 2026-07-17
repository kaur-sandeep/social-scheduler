<?php

namespace App\Repositories;

use App\Models\SocialAccount;
use App\Models\SocialPage;

class SocialAccountRepository
{
    public function upsertAccount(array $attributes, array $values): SocialAccount
    {
        if (! isset($attributes['project_id']) && session()->has('oauth_project_id')) {
            $attributes['project_id'] = session('oauth_project_id');
        }
        return SocialAccount::query()->updateOrCreate($attributes, $values);
    }

    public function upsertPage(array $attributes, array $values): SocialPage
    {
        return SocialPage::query()->updateOrCreate($attributes, $values);
    }

    public function activePagesForProject(int $userId, int $projectId)
    {
        return SocialPage::query()->whereHas('account', fn ($query) => $query->where('user_id', $userId)->where('project_id', $projectId)->where('status', 'active'))
            ->where('status', 'active')->orderBy('provider')->orderBy('page_name')->get();
    }
}
