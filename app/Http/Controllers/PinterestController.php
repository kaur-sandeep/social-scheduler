<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\PinterestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\ProjectCredentialService;

class PinterestController extends Controller
{
    public function redirect(Request $request, PinterestService $pinterest, ProjectCredentialService $credentials): RedirectResponse { if ($redirect = $this->selectOAuthProject($request, 'pinterest', $credentials)) return $redirect; return redirect()->away($pinterest->authorizationUrl()); }
    public function callback(Request $request, PinterestService $pinterest): RedirectResponse
    {
        abort_unless(hash_equals((string) session('pinterest_oauth_state'), (string) $request->input('state')), 419);
        if ($request->filled('error')) return redirect()->route('accounts.index')->with('error', $request->input('error_description', 'Pinterest connection was cancelled.'));
        $pinterest->connect($request->user()->id, $request->string('code')->toString()); $request->session()->forget('pinterest_oauth_state');
        return redirect()->route('accounts.index')->with('success', 'Pinterest account connected and boards synced.');
    }
    public function disconnect(SocialAccount $account): RedirectResponse { abort_unless($account->user_id === auth()->id() && $account->provider === 'pinterest', 403); $account->pages()->update(['status' => 'disconnected']); $account->update(['status' => 'disconnected', 'disconnected_at' => now()]); return back()->with('success', 'Pinterest account disconnected.'); }
}
