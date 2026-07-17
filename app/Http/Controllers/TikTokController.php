<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\TikTokService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\ProjectCredentialService;

class TikTokController extends Controller
{
    public function redirect(Request $request, TikTokService $tiktok, ProjectCredentialService $credentials): RedirectResponse
    {
        if ($redirect = $this->selectOAuthProject($request, 'tiktok', $credentials)) return $redirect;
        return redirect()->away($tiktok->authorizationUrl());
    }

    public function callback(Request $request, TikTokService $tiktok): RedirectResponse
    {
        abort_unless(hash_equals((string) session('tiktok_oauth_state'), (string) $request->input('state')), 419);

        if ($request->filled('error')) {
            return redirect()->route('accounts.index')->with('error', $request->input('error_description', 'TikTok connection was cancelled.'));
        }

        $tiktok->connect($request->user()->id, $request->string('code')->toString());
        $request->session()->forget('tiktok_oauth_state');

        return redirect()->route('accounts.index')->with('success', 'TikTok account connected.');
    }

    public function disconnect(SocialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === auth()->id() && $account->provider === 'tiktok', 403);

        $account->pages()->update(['status' => 'disconnected']);
        $account->update(['status' => 'disconnected', 'disconnected_at' => now()]);

        return back()->with('success', 'TikTok account disconnected.');
    }
}
