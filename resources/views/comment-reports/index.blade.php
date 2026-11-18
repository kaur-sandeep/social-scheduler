@extends('layouts.app')

@section('title', 'Comment Report')
@section('subtitle', 'Review and manage comment reports')

@section('content')
<section class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="h5 mb-1">Comment reports</h2>
                <p class="text-muted small mb-0">{{ $reportCount }} {{ \Illuminate\Support\Str::plural('report', $reportCount) }} saved</p>
            </div>
            <span class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width:44px;height:44px"><i class="bi bi-chat-square-text fs-5"></i></span>
        </div>

        @if($reports->isEmpty())
            <div class="text-center py-5">
                <div class="text-primary fs-1 mb-2"><i class="bi bi-chat-square-text"></i></div>
                <h3 class="h6">No comment reports yet</h3>
                <p class="text-muted mb-0">Select <strong>Add Comment Report</strong> to create your first report.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Project</th><th>Account type</th><th>Report date</th><th>Comments</th><th>Created at</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach($reports as $report)
                            <tr>
                                <td class="fw-semibold">{{ $report->project_name }}</td>
                                <td><span class="badge rounded-pill text-bg-light border">{{ $report->account_type }}</span></td>
                                <td>{{ $report->report_date->format('M d, Y') }}</td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#commentReportDetails{{ $report->id }}"><i class="bi bi-eye me-1"></i> View comments <span class="badge text-bg-primary ms-1">{{ $report->number_of_comments }}</span></button></td>
                                <td>{{ $report->created_at->format('M d, Y g:i A') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('comment-reports.edit', $report->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit report">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <form method="post" action="{{ route('comment-reports.destroy', $report->id) }}" class="d-inline" onsubmit="return confirm('Delete this comment report? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete report">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @foreach($reports as $report)
                <div class="modal fade" id="commentReportDetails{{ $report->id }}" tabindex="-1" aria-labelledby="commentReportDetailsTitle{{ $report->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title h5" id="commentReportDetailsTitle{{ $report->id }}">Comment report details</h2>
                                    <p class="text-muted small mb-0">{{ $report->project_name }} · {{ $report->account_type }} · {{ $report->report_date->format('M d, Y') }}</p>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                @foreach($report->posts as $post)
                                    <article class="border rounded-3 p-3 mb-3">
                                        <h3 class="h6 mb-2">Comment {{ $post->position }}</h3>
                                        <p class="text-break mb-2">{{ $post->comment_text }}</p>
                                        <a class="text-break" href="{{ $post->post_url }}" target="_blank" rel="noopener noreferrer">{{ $post->post_url }}</a>
                                    </article>
                                @endforeach
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="mt-3">{{ $reports->links() }}</div>
        @endif
    </div>
</section>

<div class="modal fade" id="addCommentReportModal" tabindex="-1" aria-labelledby="addCommentReportTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="post" action="{{ route('comment-reports.store') }}" id="commentReportForm" class="modal-content border-0 shadow">
                @csrf
                <div class="modal-header px-4">
                    <div>
                        <h2 class="modal-title h5" id="addCommentReportTitle">Add Comment Report</h2>
                        <p class="text-muted small mb-0">Enter report details and pair each comment with a post URL.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="project_name" class="form-label">Project Name <span class="text-danger">*</span></label>
                            <select id="project_name" name="project_name" class="form-select @error('project_name') is-invalid @enderror" required>
                                <option value="">Select project</option>
                                @foreach($projectNames as $projectName)
                                    <option value="{{ $projectName }}" @selected(old('project_name') === $projectName)>{{ $projectName }}</option>
                                @endforeach
                            </select>
                            @error('project_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="account_type" class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select id="account_type" name="account_type" class="form-select @error('account_type') is-invalid @enderror" required>
                                <option value="">Select account type</option>
                                @foreach($accountTypes as $type)
                                    <option value="{{ $type }}" @selected(old('account_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="report_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" id="report_date" name="report_date" max="{{ now()->toDateString() }}" class="form-control @error('report_date') is-invalid @enderror" value="{{ old('report_date', now()->toDateString()) }}" required>
                            @error('report_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="number_of_comments" class="form-label">No. of Comments <span class="text-danger">*</span></label>
                            <input type="number" id="number_of_comments" name="number_of_comments" class="form-control @error('number_of_comments') is-invalid @enderror" value="{{ old('number_of_comments', 1) }}" min="1" max="100" step="1" required>
                            <div class="form-text">Choose 1–100. A comment and URL field will be added for each entry.</div>
                            @error('number_of_comments')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                        <h3 class="h6 mb-0"><b>Comments and post URLs </b><span class="text-danger">*</span></h3>
                        <span class="badge text-bg-light border" id="entryCountBadge">1 entries</span>
                    </div>
                    @if($errors->has('comments') || $errors->has('post_urls'))
                        <div class="alert alert-danger py-2">{{ $errors->first('comments') ?: $errors->first('post_urls') }}</div>
                    @endif
                    <div id="postUrlFields" class="row g-3" aria-live="polite"></div>
                </div>
                <div class="modal-footer px-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveCommentReport"><i class="bi bi-check2-circle me-1"></i> Save Report</button>
                </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const countInput = document.getElementById('number_of_comments');
    const container = document.getElementById('postUrlFields');
    const badge = document.getElementById('entryCountBadge');
    const oldUrls = @json(old('post_urls', []));
    const oldComments = @json(old('comments', []));
    const validationErrors = @json($errors->getMessages());

    function renderRows() {
        const count = Math.max(0, Math.min(100, Number.parseInt(countInput.value, 10) || 0));
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
            comment.className = `form-control form-control-sm mb-2${validationErrors[`comments.${index}`] ? ' is-invalid' : ''}`;
            comment.required = true;
            comment.maxLength = 10000;
            comment.rows = 2;
            comment.placeholder = `Enter comment ${index + 1}`;
            comment.setAttribute('aria-label', `Comment ${index + 1}`);
            comment.value = current[index]?.comment ?? oldComments[index] ?? '';

            const commentError = document.createElement('div');
            commentError.className = 'invalid-feedback mb-2';
            commentError.textContent = validationErrors[`comments.${index}`]?.[0] ?? '';

            const url = document.createElement('input');
            url.type = 'url';
            url.name = 'post_urls[]';
            url.className = `form-control form-control-sm${validationErrors[`post_urls.${index}`] ? ' is-invalid' : ''}`;
            url.required = true;
            url.maxLength = 2048;
            url.placeholder = `https://... post URL ${index + 1}`;
            url.setAttribute('aria-label', `Recommended post URL ${index + 1}`);
            url.value = current[index]?.url ?? oldUrls[index] ?? '';

            const urlError = document.createElement('div');
            urlError.className = 'invalid-feedback';
            urlError.textContent = validationErrors[`post_urls.${index}`]?.[0] ?? '';

            card.append(title, comment, commentError, url, urlError);
            column.append(card);
            container.append(column);
        }
    }

    countInput.addEventListener('input', renderRows);
    countInput.addEventListener('change', renderRows);
    renderRows();

    @if($errors->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addCommentReportModal')).show();
    @endif
})();
</script>
@endpush
