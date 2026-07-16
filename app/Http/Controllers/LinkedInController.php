<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\LinkedInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LinkedInController extends Controller
{
    public function redirect(LinkedInService $linkedin): RedirectResponse { return redirect()->away($linkedin->authorizationUrl()); }
    public function callback(Request $request, LinkedInService $linkedin): RedirectResponse
    {
        abort_unless(hash_equals((string) session('linkedin_oauth_state'), (string) $request->input('state')), 419);
        if ($request->filled('error')) return redirect()->route('accounts.index')->with('error', $request->input('error_description', 'LinkedIn connection was cancelled.'));
        $linkedin->connect($request->user()->id, $request->string('code')->toString()); $request->session()->forget('linkedin_oauth_state');
        return redirect()->route('accounts.index')->with('success', 'LinkedIn profile connected.');
    }
    public function disconnect(SocialAccount $account): RedirectResponse { abort_unless($account->user_id === auth()->id() && $account->provider === 'linkedin', 403); $account->pages()->update(['status' => 'disconnected']); $account->update(['status' => 'disconnected', 'disconnected_at' => now()]); return back()->with('success', 'LinkedIn account disconnected.'); }
}
