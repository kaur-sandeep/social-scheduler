@extends('layouts.app') @section('title', 'Project Settings') @section('subtitle', 'Configure OAuth applications for this project only') @section('content')
<div class="panel">

  <div class="row g-3 mb-4">
       
        <div class="col-md-5">
            <form method="post" action="{{ route('project-settings.store') }}">
                <label class="form-label">Create project</label>
                <div class="input-group">@csrf
                    <input class="form-control" name="name" placeholder="e.g. Client A" required>
                    <button class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    @if($project)
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <form method="get">
                <label class="form-label">Project</label>
                <select class="form-select" name="project_id" onchange="this.form.submit()">@foreach($projects as $item)
                    <option value="{{ $item->id }}" @selected($item->id === $project->id)>{{ $item->name }}</option>@endforeach</select>
            </form>
        </div>
          @if($projects->count() > 1)
            <form method="post" action="{{ route('project-settings.destroy', $project) }}" class="mb-4">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Delete Project" data-confirm-message="Are you sure you want to delete this project? Its connected accounts and credentials will also be removed.">Delete project</button>
            </form>@endif
    </div>
  
    <form method="post" action="{{ route('project-settings.update', $project) }}">@csrf @method('PUT')
        <!-- 'instagram' => 'Instagram', -->
            @php
            $tooltips = [

            'facebook' => '
            <strong>Facebook App Setup Guide</strong><hr>

            <b>Step 1:</b> Open Meta for Developers<br>
            https://developers.facebook.com/<br><br>

            <b>Step 2:</b> Click <b>My Apps → Create App</b><br><br>

            <b>Step 3:</b> Select <b>Other → Business</b><br><br>

            <b>Step 4:</b> Fill App Name, Email and Create App.<br><br>

            <b>Step 5:</b> Add <b>Facebook Login</b> product.<br><br>

            <b>Step 6:</b> Configure the Redirect URL.<br><br>

            <b>Step 7:</b> Copy <b>App ID</b> and <b>App Secret</b>.
            ',

            'linkedin' => '
            <strong>LinkedIn App Setup Guide</strong><hr>

            <b>Step 1:</b> Open LinkedIn Developer Portal<br>
            https://www.linkedin.com/developers/apps<br><br>

            <b>Step 2:</b> Click <b>Create App</b>.<br><br>

            <b>Step 3:</b> Fill App Name, Company Page, Privacy Policy URL and Logo.<br><br>

            <b>Step 4:</b> After the app is created, open the <b>Auth</b> tab.<br><br>

            <b>Step 5:</b> Copy the <b>Client ID</b> and <b>Client Secret</b>.<br><br>

            <b>Step 6:</b> Add your Redirect URL under <b>Authorized Redirect URLs</b>.<br><br>

            <b>Step 7:</b> Request Marketing Developer Platform access if your app needs posting permissions.
            ',

            'tiktok' => '
            <strong>TikTok App Setup Guide</strong><hr>

            <b>Step 1:</b> Open TikTok Developers<br>
            https://developers.tiktok.com/<br><br>

            <b>Step 2:</b> Click <b>Manage Apps → Create App</b>.<br><br>

            <b>Step 3:</b> Enter App Name, Description and Website.<br><br>

            <b>Step 4:</b> Configure Login Kit and add your Redirect URI.<br><br>

            <b>Step 5:</b> Submit the required scopes for review (for example video upload and user info).<br><br>

            <b>Step 6:</b> Copy the <b>Client Key</b> (Client ID) and <b>Client Secret</b>.
            ',

            'pinterest' => '
            <strong>Pinterest App Setup Guide</strong><hr>

            <b>Step 1:</b> Open Pinterest Developers<br>
            https://developers.pinterest.com/<br><br>

            <b>Step 2:</b> Click <b>Create App</b>.<br><br>

            <b>Step 3:</b> Enter the App Name, Description and Website.<br><br>

            <b>Step 4:</b> Add your Redirect URI.<br><br>

            <b>Step 5:</b> Request the required permissions such as:<br>
            • pins:read<br>
            • boards:read<br>
            • user_accounts:read<br><br>

            <b>Step 6:</b> Copy the <b>App ID</b> and <b>App Secret</b>.
            ',

            'twitter' => '
            <strong>X (Twitter) App Setup Guide</strong><hr>

            <b>Step 1:</b> Open X Developer Portal<br>
            https://developer.x.com/<br><br>

            <b>Step 2:</b> Create a Project and App.<br><br>

            <b>Step 3:</b> Enable OAuth 2.0.<br><br>

            <b>Step 4:</b> Add your Callback URL and Website URL.<br><br>

            <b>Step 5:</b> Save the settings.<br><br>

            <b>Step 6:</b> Copy the <b>Client ID</b> and <b>Client Secret</b>.
            ',

            'youtube' => '
            <strong>YouTube App Setup Guide</strong><hr>

            <b>Step 1:</b> Open Google Cloud Console<br>
            https://console.cloud.google.com/<br><br>

            <b>Step 2:</b> Create a new Project.<br><br>

            <b>Step 3:</b> Enable the <b>YouTube Data API v3</b>.<br><br>

            <b>Step 4:</b> Configure the OAuth Consent Screen.<br><br>

            <b>Step 5:</b> Create an <b>OAuth Client ID</b>.<br><br>

            <b>Step 6:</b> Add your Authorized Redirect URI.<br><br>

            <b>Step 7:</b> Copy the <b>Client ID</b> and <b>Client Secret</b>.
            '

            ];
            $defaultRedirectUrls = [
                'facebook'  => 'https://socialscheduler.cogniter.com/facebook/callback',
                'linkedin'  => 'https://socialscheduler.cogniter.com/linkedin/callback',
                'tiktok'    => 'https://socialscheduler.cogniter.com/tiktok/callback',
                'pinterest' => 'https://socialscheduler.cogniter.com/pinterest/callback',
                'twitter'   => 'https://socialscheduler.cogniter.com/twitter/callback',
                'youtube'   => 'https://socialscheduler.cogniter.com/youtube/callback',
            ];
            @endphp
        @foreach(['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'twitter' => 'X (Twitter)', 'youtube' => 'YouTube'] as $key => $name) @php($credential = $credentials->get($key))
     
        <div class="border rounded p-3 mb-3">
           <h2 class="h5 d-flex align-items-center">
                {{ $name }} App

                <i class="bi bi-info-circle-fill text-primary ms-2"
                style="cursor:pointer"
                data-bs-toggle="tooltip"
                data-bs-html="true"
                data-bs-placement="right"
                data-bs-custom-class="facebook-tooltip"
                title="{{ $tooltips[$key] }}">
                </i>
            </h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Client ID</label>
                    <input class="form-control" name="credentials[{{ $key }}][client_id]" value="{{ old("credentials.$key.client_id", $credential?->client_id) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Client Secret</label>
                    <input class="form-control" type="password" name="credentials[{{ $key }}][client_secret]" placeholder="{{ $credential ? 'Leave blank to keep current secret' : '' }}">
                </div>
                <!-- <div class="col-md-4">

                    <label class="form-label">Redirect URL</label>
                    <input class="form-control" type="url" name="credentials[{{ $key }}][redirect_uri]" value="{{ old("credentials.$key.redirect_uri", $credential?->redirect_uri) }}">
                </div> -->

                        <div class="col-md-4">
               

                <label class="form-label">Redirect URL</label>
                        <input
                class="form-control"
                type="url"
                name="credentials[{{ $key }}][redirect_uri]"
                value="{{ $defaultRedirectUrls[$key] }}"
                readonly
            >
                <small class="text-muted">
                    Copy this URL and add it as the Redirect URI while creating your {{ $name }} application.
                </small>
            </div>


                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="credentials[{{ $key }}][status]">
                        <option value="active" @selected(($credential?->status ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected($credential?->status === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        @endforeach
        <button class="btn btn-primary">Save credentials</button>
    </form>
    @else
      <div class="alert alert-info">Create a project to configure its social application credentials.</div>
    @endif

    @if($deletedProjects->isNotEmpty())
      <div class="border-top mt-4 pt-4"><h2 class="h5">Deleted projects</h2><p class="small text-muted">Restoring a project also restores its connected accounts, pages, credentials, and posts.</p>
      @foreach($deletedProjects as $deletedProject)<div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2"><span>{{ $deletedProject->name }}</span><div class="d-flex gap-2"><form method="post" action="{{ route('project-settings.restore', $deletedProject->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary">Restore</button></form>@if(auth()->user()->is_admin)<form method="post" action="{{ route('project-settings.force-destroy', $deletedProject->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Permanently Delete Project" data-confirm-message="This project and all of its related records will be permanently deleted.">Delete permanently</button></form>@endif</div></div>@endforeach</div>
    @endif
</div>
@push('styles')
<style>
.tooltip {
    --bs-tooltip-max-width: 400px;
}

.tooltip-inner {
    max-width: 400px !important;
    width: 400px;
    text-align: left;
    white-space: normal;
    padding: 18px;
    line-height: 1.6;
    font-size: 14px;
}
</style>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
@endsection
