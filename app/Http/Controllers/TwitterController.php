<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\TwitterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\ProjectCredentialService;

class TwitterController extends Controller
{
    public function redirect(Request $request, TwitterService $twitter, ProjectCredentialService $credentials): RedirectResponse { if ($redirect = $this->selectOAuthProject($request, 'twitter', $credentials)) return $redirect; return redirect()->away($twitter->authorizationUrl()); }
    public function callback(Request $request, TwitterService $twitter): RedirectResponse
    {
        abort_unless(hash_equals((string) session('twitter_oauth_state'), (string) $request->input('state')), 419);
        if ($request->filled('error')) return redirect()->route('accounts.index')->with('error', $request->input('error_description', 'X connection was cancelled.'));
        $twitter->connect($request->user()->id, $request->string('code')->toString(), (string) session('twitter_oauth_verifier')); $request->session()->forget(['twitter_oauth_state', 'twitter_oauth_verifier']);
        return redirect()->route('accounts.index')->with('success', 'X account connected.');
    }
    public function disconnect(SocialAccount $account): RedirectResponse { abort_unless($account->user_id === auth()->id() && $account->provider === 'twitter', 403); $account->pages()->update(['status' => 'disconnected']); $account->update(['status' => 'disconnected', 'disconnected_at' => now()]); return back()->with('success', 'X account disconnected.'); }
}
