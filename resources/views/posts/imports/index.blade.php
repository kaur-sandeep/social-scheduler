@extends('layouts.app')

@section('title', 'Import Posts')
@section('subtitle', 'Upload a prepared spreadsheet; processing continues safely in the background.')

@section('content')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0"><div class="card-body p-4">
            <h5 class="mb-2">Bulk post import</h5><p class="text-muted small">1. Download the template &nbsp; 2. Fill each row &nbsp; 3. Upload and import.</p>
            <a href="{{ route('posts.imports.template') }}" class="btn btn-outline-primary w-100 mb-4"><i class="bi bi-download"></i> Download Sample Template</a>
            <form action="{{ route('posts.imports.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <label class="form-label fw-semibold">Completed spreadsheet</label>
                <input class="form-control @error('import_file') is-invalid @enderror" type="file" name="import_file" accept=".xlsx,.xls,.csv" required>
                <div class="form-text">Excel or CSV, maximum 20 MB. Use the template headings unchanged.</div>
                @error('import_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100 mt-4"><i class="bi bi-cloud-arrow-up"></i> Import Posts</button>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0"><div class="card-body p-4">
            <h5>Import history</h5>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>ID / File</th><th>Status</th><th>Progress</th><th>Results</th><th></th></tr></thead><tbody>
            @forelse($imports as $import)
                <tr data-import-id="{{ $import->id }}"><td><strong>#{{ $import->id }}</strong><br><span class="small text-muted">{{ $import->original_filename }}<br>{{ $import->created_at->format('d M Y H:i') }}</span></td><td class="status text-capitalize">{{ $import->status }}</td><td style="min-width:130px"><div class="progress" style="height:7px"><div class="progress-bar" style="width:{{ $import->total_rows ? min(100, round($import->processed_rows / $import->total_rows * 100)) : 0 }}%"></div></div><small class="progress-label">{{ $import->processed_rows }}/{{ $import->total_rows }}</small></td><td class="results"><span class="text-success">{{ $import->successful_rows }} imported</span><br><span class="text-danger">{{ $import->failed_rows }} failed</span>@if($import->skipped_rows)<br><span class="text-muted">{{ $import->skipped_rows }} skipped</span>@endif</td><td>@if($import->failed_rows)<a class="btn btn-sm btn-outline-danger" href="{{ route('posts.imports.errors', $import) }}">Errors</a>@endif</td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-4">No imports yet.</td></tr>@endforelse
            </tbody></table></div>
            <div class="mt-3">{{ $imports->links() }}</div>
        </div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
setInterval(() => document.querySelectorAll('[data-import-id]').forEach(row => {
  const id=row.dataset.importId; fetch(`/posts/import/${id}/progress`, {headers:{Accept:'application/json'}}).then(r=>r.json()).then(data => {
    row.querySelector('.status').textContent=data.status;
    row.querySelector('.progress-bar').style.width=data.percent+'%'; row.querySelector('.progress-label').textContent=`${data.processed}/${data.total}`;
    row.querySelector('.results').innerHTML=`<span class="text-success">${data.successful} imported</span><br><span class="text-danger">${data.failed} failed</span>${data.skipped ? `<br><span class="text-muted">${data.skipped} skipped</span>` : ''}`;
  }).catch(()=>{});
}), 5000);
</script>
@endpush
