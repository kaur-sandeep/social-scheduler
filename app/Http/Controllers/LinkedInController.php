<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\LinkedInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\ProjectCredentialService;

class LinkedInController extends Controller
{
    public function redirect(Request $request, LinkedInService $linkedin, ProjectCredentialService $credentials): RedirectResponse { if ($redirect = $this->selectOAuthProject($request, 'linkedin', $credentials)) return $redirect; return redirect()->away($linkedin->authorizationUrl()); }
    public function callback(Request $request, LinkedInService $linkedin): RedirectResponse
    {
        abort_unless(hash_equals((string) session('linkedin_oauth_state'), (string) $request->input('state')), 419);
        if ($request->filled('error')) return redirect()->route('accounts.index')->with('error', $request->input('error_description', 'LinkedIn connection was cancelled.'));
        $account = $linkedin->connect($request->user()->id, $request->string('code')->toString());
        $request->session()->forget('linkedin_oauth_state');

        try {
            $companyPages = $linkedin->syncCompanyPages($account);
        } catch (\RuntimeException $exception) {
            report($exception);

            return redirect()->route('accounts.index')
                ->with('success', 'LinkedIn profile connected. Your personal-profile posting is unchanged.')
                ->with('error', 'Company Pages were not synced. Enable the Community Management API and the r_organization_admin and w_organization_social permissions in LinkedIn, then reconnect.');
        }

        return redirect()->route('accounts.index')->with('success', 'LinkedIn profile connected and '.$companyPages.' '.\Illuminate\Support\Str::plural('Company Page', $companyPages).' synced.');
    }
    public function disconnect(SocialAccount $account): RedirectResponse { abort_unless($account->user_id === auth()->id() && $account->provider === 'linkedin', 403); $account->pages()->update(['status' => 'disconnected']); $account->update(['status' => 'disconnected', 'disconnected_at' => now()]); return back()->with('success', 'LinkedIn account disconnected.'); }
}
