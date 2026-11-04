<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\ProjectCredentialService;
use App\Services\Social\InstagramLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class InstagramController extends Controller
{
    public function redirect(Request $request, InstagramLoginService $instagram, ProjectCredentialService $credentials): RedirectResponse
    {
        if ($redirect = $this->selectOAuthProject($request, 'instagram', $credentials)) return $redirect;
        return redirect()->away($instagram->authorizationUrl());
    }

    public function callback(Request $request, InstagramLoginService $instagram): RedirectResponse
    {
        $projectId = (int) session('oauth_project_id');
        abort_unless($request->filled('state') && hash_equals((string) session('instagram_oauth_state'), (string) $request->query('state')), 419);
        if ($request->filled('error')) {
            $message = $request->query('error_description', 'Instagram connection was cancelled.');
            $request->session()->forget('instagram_oauth_state');
            return redirect()->route('accounts.index', ['project_id' => $projectId])->with('error', $message);
        }

        $request->validate(['code' => ['required', 'string']]);
        try {
            $instagram->handleCallback($request->user()->id, (string) $request->query('code'));
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('accounts.index', ['project_id' => $projectId])->with('error', 'Instagram could not be connected: '.$exception->getMessage());
        } finally {
            $request->session()->forget('instagram_oauth_state');
        }

        return redirect()->route('accounts.index', ['project_id' => $projectId])->with('success', 'Instagram professional account connected.');
    }

    public function disconnect(SocialAccount $account): RedirectResponse
    {
        abort_unless($account->user_id === auth()->id() && $account->provider === 'instagram', 403);
        $account->pages()->update(['status' => 'disconnected']);
        $account->update(['status' => 'disconnected', 'disconnected_at' => now()]);
        return back()->with('success', 'Instagram account disconnected.');
    }
}
