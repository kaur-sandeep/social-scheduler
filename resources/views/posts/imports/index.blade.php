@extends('layouts.app')

@section('title', 'Import Posts')
@section('subtitle', 'Upload a prepared spreadsheet; processing continues safely in the background.')

@section('content')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0"><div class="card-body p-4">
            <h5 class="mb-2">Bulk post import</h5><p class="text-muted small">1. Download the template &nbsp; 2. Fill each row &nbsp; 3. Upload and import.</p>
            <a href="{{ route('posts.imports.template') }}" class="btn btn-outline-primary w-100 mb-4"><i class="bi bi-download"></i> Download Sample Template</a>
            <div class="alert alert-info small mb-4" role="note">
                <strong>Template instructions</strong>
                <ul class="mb-0 mt-2 ps-3">
                    <li>Choose the <em>Account/Page</em> with its social platform shown, for example <code>Facebook — Social Scheduler</code>.</li>
                    <li>For Instagram, select <em>Image Post</em>, <em>Carousel</em>, or <em>Reel</em> in the new <em>Instagram Content Type</em> column. Choose <em>Reel</em> for an MP4 or MOV reel video.</li>
                    <li>For an Instagram carousel, enter 2–10 Dropbox media URLs in <em>Media URL</em>, separated by <code>|</code>.</li>
                    <li>Use <code>YYYY-MM-DD</code> for Schedule Date, for example <code>2026-07-23</code>.</li>
                    <li>Use 24-hour <code>HH:MM</code> for Schedule Time, for example <code>09:00</code> or <code>18:30</code>.</li>
                    <li><strong>LinkedIn:</strong> if you add media, use one JPEG, PNG, or GIF image only; video uploads are not supported.</li>
                    <li><strong>TikTok:</strong> one MP4, MOV, or WebM video. <strong>YouTube:</strong> one video. <strong>Pinterest:</strong> one image.</li>
                    <li><strong>X (Twitter):</strong> leave Media URL empty; media uploads are not enabled.</li>
                </ul>
            </div>
            <div class="card border-primary mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-2">
                        <i class="bi bi-stars"></i> Generate Bulk Posts with AI
                    </h5>

                        <div class="alert alert-info small">
                            <strong>How it works:</strong>

                            <ol class="mb-0 mt-2 ps-3">
                                <li>Enter your instructions and generate the Excel file.</li>
                                <li>Download and review the generated posts.</li>
                                <li>Create/select your images or videos.</li>
                                <li>Upload your media to Dropbox.</li>
                                <li>Add the Dropbox URL(s) in the <strong>Media URL</strong> column.</li>
                                <li>Upload the completed spreadsheet below.</li>
                            </ol>
                        </div>

                    <form id="aiBulkPostForm">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">AI Prompt</label>

                            <textarea
                                name="prompt"
                                id="aiPrompt"
                                class="form-control"
                                rows="5"
                                placeholder="Example: Create 20 Facebook posts for a photography business. Use a professional and friendly tone. Schedule 2 posts per day starting from 1 September 2026."
                                required
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            id="generateAiPosts"
                            class="btn btn-primary w-100"
                        >
                            <i class="bi bi-stars"></i>
                            Generate Bulk Post File
                        </button>
                    </form>

                    <div id="aiGenerationStatus" class="mt-3"></div>
                </div>
            </div>
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
document.getElementById('aiBulkPostForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const form = this;
    const button = document.getElementById('generateAiPosts');
    const status = document.getElementById('aiGenerationStatus');
    const prompt = document.getElementById('aiPrompt').value.trim();

    if (!prompt) {
        status.innerHTML = `
            <div class="alert alert-warning">
                Please enter a prompt.
            </div>
        `;
        return;
    }

    const csrfToken = form.querySelector('input[name="_token"]').value;

    button.disabled = true;
    button.innerHTML = `
        <span class="spinner-border spinner-border-sm"></span>
        Generating...
    `;

    status.innerHTML = `
        <div class="alert alert-info">
            AI is generating your bulk posts. Please wait...
        </div>
    `;

    fetch('{{ route("posts.imports.ai.generate") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            prompt: prompt,
            _token: csrfToken
        })
    })
    .then(async response => {
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Unable to generate the file.');
        }

        return data;
    })
    .then(data => {

        if (data.success) {

            status.innerHTML = `
                <div class="alert alert-success">
                    <strong>Bulk post file generated successfully.</strong>
                    <br>
                    <a href="${data.download_url}"
                       class="btn btn-sm btn-success mt-2">
                        <i class="bi bi-download"></i>
                        Download Excel File
                    </a>
                </div>
            `;

        } else {

            status.innerHTML = `
                <div class="alert alert-danger">
                    ${data.message || 'Unable to generate the file.'}
                </div>
            `;
        }

    })
    .catch(error => {

        console.error(error);

        status.innerHTML = `
            <div class="alert alert-danger">
                ${error.message || 'Something went wrong while generating the file.'}
            </div>
        `;

    })
    .finally(() => {

        button.disabled = false;

        button.innerHTML = `
            <i class="bi bi-stars"></i>
            Generate Bulk Post File
        `;
    });
});
</script>
@endpush
