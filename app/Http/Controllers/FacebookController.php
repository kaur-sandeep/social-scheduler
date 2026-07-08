<?php

namespace App\Http\Controllers;

use App\Http\Requests\FacebookCallbackRequest;
use App\Models\SocialAccount;
use App\Services\Social\FacebookService;
use Illuminate\Http\RedirectResponse;

class FacebookController extends Controller
{
    public function redirect(FacebookService $facebook): RedirectResponse
    {
        return redirect()->away($facebook->authorizationUrl());
    }

    public function callback(FacebookCallbackRequest $request, FacebookService $facebook): RedirectResponse
    {
        abort_unless(hash_equals((string) session('facebook_oauth_state'), (string) $request->state), 419);

        if ($request->filled('error')) {
            return redirect()->route('accounts.index')->with('error', $request->string('error_description', 'Facebook connection was cancelled.'));
        }

        $facebook->handleCallback($request->user()->id, $request->string('code'));
        $request->session()->forget('facebook_oauth_state');

        return redirect()->route('accounts.index')->with('success', 'Facebook account connected and pages synced.');
    }

    public function disconnect(SocialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === auth()->id(), 403);

        $account->update([
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ]);

        return back()->with('success', 'Facebook account disconnected.');
    }
}
