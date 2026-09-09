<?php

namespace App\Repositories;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use Illuminate\Support\Facades\Crypt;

class SocialAccountRepository
{
    public function upsertAccount(array $attributes, array $values): SocialAccount
    {
        if (! isset($attributes['project_id']) && session()->has('oauth_project_id')) {
            $attributes['project_id'] = session('oauth_project_id');
        }

        // Database unique indexes include soft-deleted rows. Reconnecting an
        // account must therefore restore a previously removed record instead
        // of attempting to insert the same provider identity again.
        $account = SocialAccount::withTrashed()->where($attributes)->first();

        if ($account) {
            // Use a query update here. A token written with a previous APP_KEY
            // (or before encrypted casts existed) cannot be decrypted by the
            // model while it determines dirty attributes. Replacing it with a
            // freshly encrypted token must not require reading the old one.
            SocialAccount::withTrashed()->whereKey($account->id)->update(
                array_merge($this->accountValuesForDatabase($values), ['deleted_at' => null])
            );

            return $account->refresh();
        }

        return SocialAccount::query()->create(array_merge($attributes, $values));
    }

    public function upsertPage(array $attributes, array $values): SocialPage
    {
        // A soft-deleted page still participates in the unique index, but is
        // hidden by Eloquent's default query scope.
        $page = SocialPage::withTrashed()->where($attributes)->first();

        if ($page) {
            // See upsertAccount(): do not decrypt an old invalid payload just
            // to overwrite it with the newly authorized access token.
            SocialPage::withTrashed()->whereKey($page->id)->update(
                array_merge($this->pageValuesForDatabase($values), ['deleted_at' => null])
            );

            return $page->refresh();
        }

        return SocialPage::query()->create(array_merge($attributes, $values));
    }

    private function accountValuesForDatabase(array $values): array
    {
        foreach (['user_access_token', 'refresh_token'] as $attribute) {
            if (array_key_exists($attribute, $values) && $values[$attribute] !== null) {
                $values[$attribute] = Crypt::encryptString($values[$attribute]);
            }
        }

        return $values;
    }

    private function pageValuesForDatabase(array $values): array
    {
        if (array_key_exists('page_access_token', $values) && $values['page_access_token'] !== null) {
            $values['page_access_token'] = Crypt::encryptString($values['page_access_token']);
        }

        if (array_key_exists('permissions', $values) && is_array($values['permissions'])) {
            $values['permissions'] = json_encode($values['permissions'], JSON_THROW_ON_ERROR);
        }

        return $values;
    }

    public function activePagesForProject(int $userId, int $projectId)
    {
        return SocialPage::query()->whereHas('account', fn ($query) => $query->where('user_id', $userId)->where('project_id', $projectId)->where('status', 'active'))
            ->where('status', 'active')->orderBy('provider')->orderBy('page_name')->get();
    }
}
