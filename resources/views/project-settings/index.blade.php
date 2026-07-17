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
        @foreach(['facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'twitter' => 'X (Twitter)', 'youtube' => 'YouTube'] as $key => $name) @php($credential = $credentials->get($key))
        <div class="border rounded p-3 mb-3">
            <h2 class="h5">{{ $name }} App</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Client ID</label>
                    <input class="form-control" name="credentials[{{ $key }}][client_id]" value="{{ old(" credentials.$key.client_id ", $credential?->client_id) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Client Secret</label>
                    <input class="form-control" type="password" name="credentials[{{ $key }}][client_secret]" placeholder="{{ $credential ? 'Leave blank to keep current secret' : '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Redirect URL</label>
                    <input class="form-control" type="url" name="credentials[{{ $key }}][redirect_uri]" value="{{ old(" credentials.$key.redirect_uri ", $credential?->redirect_uri) }}">
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
</div>
@endsection