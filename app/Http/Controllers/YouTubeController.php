<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\YouTubeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class YouTubeController extends Controller
{
    public function redirect(YouTubeService $youtube): RedirectResponse { return redirect()->away($youtube->authorizationUrl()); }
    public function callback(Request $request, YouTubeService $youtube): RedirectResponse
    {
        $request->validate(['code' => ['required_without:error', 'string'], 'state' => ['required', 'string'], 'error' => ['nullable', 'string']]);
        abort_unless(hash_equals((string) session('youtube_oauth_state'), (string) $request->input('state')), 419);
        if ($request->filled('error')) return redirect()->route('accounts.index')->with('error', $request->input('error_description', 'YouTube connection was cancelled.'));
        $youtube->connect($request->user()->id, $request->string('code')->toString());
        $request->session()->forget('youtube_oauth_state');
        return redirect()->route('accounts.index')->with('success', 'YouTube channel connected.');
    }
    public function disconnect(SocialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === auth()->id() && $account->provider === 'youtube', 403);
        $account->update(['status' => 'disconnected', 'disconnected_at' => now()]);
        return back()->with('success', 'YouTube channel disconnected.');
    }
}
