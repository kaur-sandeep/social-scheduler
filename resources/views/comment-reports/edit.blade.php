@extends('layouts.app')

@section('title', 'Comment Report')
@section('subtitle', 'Review and manage comment reports')

@section('content')
<section class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-end mb-4">
            <a href="{{ route('comment-reports.index') }}" class="btn btn-outline-secondary">Back to reports</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">Please correct the errors below. {{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('comment-reports.update', $report->id) }}" id="editCommentReportForm">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="project_name" class="form-label">Project Name <span class="text-danger">*</span></label>
                    @php($selectedProject = old('project_name', $report->project_name))
                    <select id="project_name" name="project_name" class="form-select @error('project_name') is-invalid @enderror" required>
                        <option value="">Select project</option>
                        @foreach($projectNames as $projectName)
                            <option value="{{ $projectName }}" @selected(strcasecmp($selectedProject, $projectName) === 0)>{{ $projectName }}</option>
                        @endforeach
                    </select>
                    @error('project_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="account_type" class="form-label">Account Type <span class="text-danger">*</span></label>
                    <select id="account_type" name="account_type" class="form-select @error('account_type') is-invalid @enderror" required>
                        <option value="">Select account type</option>
                        @foreach($accountTypes as $type)
                            <option value="{{ $type }}" @selected(old('account_type', $report->account_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="report_date" class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" id="report_date" name="report_date" max="{{ now()->toDateString() }}" class="form-control @error('report_date') is-invalid @enderror" value="{{ old('report_date', $report->report_date->toDateString()) }}" required>
                    @error('report_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="number_of_comments" class="form-label">No. of Comments <span class="text-danger">*</span></label>
                    <input type="number" id="number_of_comments" name="number_of_comments" class="form-control @error('number_of_comments') is-invalid @enderror" value="{{ old('number_of_comments', $report->posts->count()) }}" min="1" max="100" step="1" required>
                    @error('number_of_comments')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                        <h3 class="h6 mb-0"><b>Comments and post URLs </b><span class="text-danger">*</span></h3>
                        <span class="badge text-bg-light border" id="entryCountBadge">1 entries</span>
                
                <span class="badge text-bg-light border" id="entryCountBadge"></span>
            </div>
            <div id="postUrlFields" class="row g-3" aria-live="polite"></div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('comment-reports.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const countInput = document.getElementById('number_of_comments');
    const container = document.getElementById('postUrlFields');
    const badge = document.getElementById('entryCountBadge');
    const initialComments = @json(old('comments', $report->posts->pluck('comment_text')->values()));
    const initialUrls = @json(old('post_urls', $report->posts->pluck('post_url')->values()));

    function renderRows() {
        const count = Math.max(1, Math.min(100, Number.parseInt(countInput.value, 10) || 1));
        const current = Array.from(container.querySelectorAll('.comment-report-entry')).map(row => ({
            comment: row.querySelector('textarea').value,
            url: row.querySelector('input').value,
        }));
        container.replaceChildren();
        badge.textContent = `${count} ${count === 1 ? 'entry' : 'entries'}`;

        for (let index = 0; index < count; index++) {
            const column = document.createElement('div');
            column.className = 'col-12 col-lg-6';
            const card = document.createElement('div');
            card.className = 'comment-report-entry h-100 border rounded-3 p-3 bg-light-subtle';
            const title = document.createElement('div');
            title.className = 'small fw-semibold text-secondary mb-2';
            title.textContent = `COMMENT ${index + 1}`;

            const comment = document.createElement('textarea');
            comment.name = 'comments[]';
            comment.className = 'form-control form-control-sm mb-2';
            comment.required = true;
            comment.maxLength = 10000;
            comment.rows = 2;
            comment.placeholder = `Enter comment ${index + 1}`;
            comment.setAttribute('aria-label', `Comment ${index + 1}`);
            comment.value = current[index]?.comment ?? initialComments[index] ?? '';

            const url = document.createElement('input');
            url.type = 'url';
            url.name = 'post_urls[]';
            url.className = 'form-control form-control-sm';
            url.required = true;
            url.maxLength = 2048;
            url.placeholder = `https://... post URL ${index + 1}`;
            url.setAttribute('aria-label', `Post URL ${index + 1}`);
            url.value = current[index]?.url ?? initialUrls[index] ?? '';

            card.append(title, comment, url);
            column.append(card);
            container.append(column);
        }
    }

    countInput.addEventListener('input', renderRows);
    countInput.addEventListener('change', renderRows);
    renderRows();
})();
</script>
@endpush
