<?php

namespace App\Repositories;

use App\Models\SocialAccount;
use App\Models\SocialPage;

class SocialAccountRepository
{
    public function upsertAccount(array $attributes, array $values): SocialAccount
    {
        return SocialAccount::query()->updateOrCreate($attributes, $values);
    }

    public function upsertPage(array $attributes, array $values): SocialPage
    {
        return SocialPage::query()->updateOrCreate($attributes, $values);
    }
}
