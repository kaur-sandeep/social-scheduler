<?php

namespace App\Services;

use App\Models\SocialAccount;
use Carbon\CarbonInterface;

class TokenService
{
    public function isExpired(SocialAccount $account): bool
    {
        return $account->token_expires_at instanceof CarbonInterface
            && $account->token_expires_at->isPast();
    }

    public function markDisconnected(SocialAccount $account): void
    {
        $account->forceFill([
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ])->save();
    }
}
