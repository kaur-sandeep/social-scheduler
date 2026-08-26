<?php

namespace App\Http\Controllers\Admin;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AiPostExport;
use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostImportRequest;
use App\Jobs\ProcessPostImport;
use App\Models\PostImport;
use App\Models\SocialPage;
use App\Repositories\ProjectRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Services\AiPostGeneratorService;
use Illuminate\Support\Facades\Log;
use App\Models\Project;
class PostImportController extends Controller
{
    private const ACCOUNT_SEPARATOR = "\xE2\x80\x94";

    public function index(Request $request, ProjectRepository $projects)
    {
        return view('posts.imports.index', ['imports' => PostImport::where('user_id', $request->user()->id)->latest()->paginate(15), 'projects' => $projects->projectsFor($request->user()), 'pages' => SocialPage::query()->whereHas('account', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))->orderBy('provider')->orderBy('page_name')->get()]);
    }

    public function store(StorePostImportRequest $request): RedirectResponse
    {
        $upload = $request->file('import_file');
        $import = PostImport::create(['user_id' => $request->user()->id, 'original_filename' => $upload->getClientOriginalName(), 'file_path' => $upload->store('post-imports', 'local')]);
        ProcessPostImport::dispatch($import);
        return redirect()->route('posts.imports.index')->with('success', "Import #{$import->id} has been queued and will continue in the background.");
    }

    public function progress(Request $request, PostImport $import): JsonResponse
    {
        abort_unless($import->user_id === $request->user()->id, 403);
        $import->refresh();
        return response()->json(['id' => $import->id, 'status' => $import->status, 'total' => $import->total_rows, 'processed' => $import->processed_rows, 'successful' => $import->successful_rows, 'failed' => $import->failed_rows, 'skipped' => $import->skipped_rows, 'percent' => $import->total_rows ? min(100, round($import->processed_rows / $import->total_rows * 100)) : 0]);
    }

    public function errors(Request $request, PostImport $import)
    {
        abort_unless($import->user_id === $request->user()->id, 403);
        return response()->streamDownload(function () use ($import) { $out = fopen('php://output', 'w'); fputcsv($out, ['Row Number', 'Project', 'Platform', 'Account', 'Error Message']); foreach ($import->errors()->orderBy('row_number')->cursor() as $error) fputcsv($out, [$error->row_number, $error->project, $error->platform, $error->account, $error->error_message]); fclose($out); }, 'post-import-'.$import->id.'-errors.csv', ['Content-Type' => 'text/csv']);
    }

    public function template(Request $request, ProjectRepository $projects)
    {
        return Excel::download(new AiPostExport([], $projects->projectsFor($request->user())->pluck('name')->values()->all(), $this->accountOptions($request->user())), 'social-post-import-template.xlsx');

        $book = new Spreadsheet(); $sheet = $book->getActiveSheet(); $sheet->setTitle('Posts');
        $headings = ['Project', 'Platform', 'Instagram Content Type', 'Account/Page', 'Content', 'Media URL', 'Schedule Date', 'Schedule Time', 'Timezone', 'Status'];
        $sheet->fromArray($headings, null, 'A1'); $sheet->freezePane('A2');
        $sheet->getStyle('A1:J1')->getFont()->setBold(true); $sheet->getStyle('A1:J1')->getFill()->setFillType('solid')->getStartColor()->setRGB('D9EAF7');
        foreach (range('A', 'J') as $column) $sheet->getColumnDimension($column)->setWidth($column === 'E' ? 48 : 22);
        // Let Excel accept normal date/time entry while consistently displaying the values expected by the importer.
        $sheet->getStyle('G2:G10000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('H2:H10000')->getNumberFormat()->setFormatCode('HH:mm');
        $list = $book->createSheet(); $list->setTitle('Lists'); $list->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $projectNames = $projects->projectsFor($request->user())->pluck('name')->values()->all();
        $accounts = SocialPage::query()->whereHas('account', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))->orderBy('provider')->orderBy('page_name')->get()->flatMap(function (SocialPage $page) {
            $accounts = [self::platformLabel($page->provider).' — '.($page->provider === 'instagram' && $page->instagram_username ? '@'.$page->instagram_username : $page->page_name)];
            if ($page->instagram_business_id && $page->instagram_username) $accounts[] = 'Instagram — @'.$page->instagram_username;
            return $accounts;
        })->unique()->values()->all();
        $lists = [$projectNames, ['Facebook','Instagram','LinkedIn','X (Twitter)','Pinterest','Threads','YouTube'], ['Image Post','Carousel','Reel'], $accounts, ['Asia/Kolkata','UTC','America/New_York','Europe/London','Australia/Sydney'], ['Draft','Pending']];
        foreach ($lists as $column => $values) foreach ($values as $row => $value) $list->setCellValueByColumnAndRow($column + 1, $row + 1, $value);
        foreach (['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'I' => 'E', 'J' => 'F'] as $target => $source) { $validation = new DataValidation(); $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank($target === 'C')->setShowDropDown(true)->setFormula1("Lists!\${$source}\$1:\${$source}\$".max(1, count($lists[array_search($target, ['A','B','C','D','I','J'])]))); $sheet->setDataValidation("{$target}2:{$target}10000", $validation); }
        $sheet->setCellValue('H2', '09:00'); $sheet->setCellValue('I2', 'Asia/Kolkata'); $sheet->setCellValue('J2', 'Pending');
        return response()->streamDownload(function () use ($book) { (new Xlsx($book))->save('php://output'); }, 'social-post-import-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private static function platformLabel(string $provider): string
    {
        return match ($provider) {
            'twitter' => 'X (Twitter)',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            default => ucfirst($provider),
        };
    }

    private function accountOptions($user): array
    {
        return collect($this->aiAccountOptions($user))->pluck('account')->unique()->values()->all();
    }

    private function aiAccountOptions($user): array
    {
        return SocialPage::query()
            ->with('account.project')
            ->whereHas('account', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->orderBy('provider')->orderBy('page_name')->get()
            ->flatMap(function (SocialPage $page) {
                $project = $page->account?->project?->name;
                $account = $page->provider === 'instagram' && $page->instagram_username ? '@'.$page->instagram_username : $page->page_name;
                $options = $project && $account ? [[
                    'project' => $project,
                    'platform' => $page->provider,
                    'account' => self::platformLabel($page->provider).' — '.$account,
                ]] : [];
                if ($project && $page->instagram_business_id && $page->instagram_username && $page->provider !== 'instagram') {
                    $options[] = ['project' => $project, 'platform' => 'instagram', 'account' => 'Instagram — @'.$page->instagram_username];
                }
                return $options;
            })->map(function (array $option) {
                $option['account'] = str_replace("\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D", self::ACCOUNT_SEPARATOR, $option['account']);
                return $option;
            })->unique(fn (array $option) => strtolower($option['project'].'|'.$option['platform'].'|'.$option['account']))->values()->all();
    }
        public function generateAiBulkPosts(
            Request $request,
            AiPostGeneratorService $aiPostGenerator
        ) {
            $request->validate([
                'prompt' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

            try {

                $user = auth()->user();

                /*
                * Get projects belonging to current user.
                */
                $projects = Project::query()
                    ->where('user_id', $user->id)
                    ->pluck('name')
                    ->values()
                    ->all();

                if (empty($projects)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have any projects available.',
                    ], 422);
                }

               $accounts = SocialPage::query()
                ->whereHas('account', function ($query) use ($user) {
                    $query
                        ->where('user_id', $user->id)
                        ->where('status', 'active');
                })
                ->get()
                ->map(function ($page) {

                    $accountName = $page->page_name;

                    if (
                        empty($accountName) &&
                        ! empty($page->instagram_username)
                    ) {
                        $accountName = '@' . $page->instagram_username;
                    }

                    return [
                        'platform' => $page->provider,
                        'account' => $accountName,
                    ];
                })
                ->values()
                ->all();

            $accounts = $this->aiAccountOptions($user);
            if (empty($accounts)) {
                return response()->json(['success' => false, 'message' => 'Connect an active social account before generating import-ready posts.'], 422);
            }

            /*
            * Social accounts are optional for AI generation.
            *
            * If accounts exist:
            *   AI can select platform/account.
            *
            * If no accounts exist:
            *   AI should still generate the posts.
            *   Platform/account fields will remain empty.
            */
            $hasSocialAccounts = ! empty($accounts);
                /*
                * Send prompt + actual user context to AI.
                */
                $posts = $aiPostGenerator->generate(
                    $request->prompt,
                    [
                        'projects' => $projects,
                        'accounts' => $accounts,
                    ]
                );

                $posts = array_map(function (array $post): array {
                    if (isset($post['account/page'])) {
                        $post['account/page'] = str_replace("\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D", self::ACCOUNT_SEPARATOR, (string) $post['account/page']);
                    }

                    return $post;
                }, $posts);

                if (empty($posts)) {
                    throw new \RuntimeException(
                        'AI did not generate any posts.'
                    );
                }

                /*
                * Validate AI output before creating Excel.
                */
                $this->validateAiPosts(
                    $posts,
                    $projects,
                    $accounts
                );

                /*
                * Create unique filename.
                */
                $filename =
                    'ai-bulk-posts-'.
                    $user->id.'-'.
                    now()->format('YmdHis').
                    '.xlsx';

                $path = 'post-imports/'.$filename;

                /*
                * Create Excel file.
                */
                Excel::store(
                    new AiPostExport($posts, $projects, $this->accountOptions($user)),
                    $path,
                    'public'
                );

                /*
                * Generate download URL.
                */
                $downloadUrl = Storage::disk('public')->url($path);

                return response()->json([
                    'success' => true,
                    'message' => 'Bulk post file generated successfully.',
                    'download_url' => $downloadUrl,
                    'filename' => $filename,
                    'count' => count($posts),
                ]);

            } catch (\Throwable $e) {

                Log::error(
                    'AI bulk post generation failed',
                    [
                        'user_id' => auth()->id(),
                        'prompt' => $request->prompt,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        private function validateAiPosts(
            array $posts,
            array $projects,
            array $accounts
        ): void {

            $allowedPlatforms = [
                'facebook',
                'instagram',
                'linkedin',
                'twitter',
                'pinterest',
                'threads',
                'youtube',
            ];

            $projectNames = collect($projects)
                ->map(fn ($name) => mb_strtolower(trim($name)))
                ->all();

            $availableAccounts = collect($accounts)
                ->map(function ($account) {
                    return [
                        'project' => mb_strtolower(trim((string) $account['project'])),
                        'platform' => mb_strtolower(
                            trim((string) $account['platform'])
                        ),
                        'account' => mb_strtolower(
                            trim((string) $account['account'])
                        ),
                    ];
                })
                ->all();

            foreach ($posts as $index => $post) {

                $row = $index + 1;

                $required = [
                    'project',
                    'platform',
                    'account/page',
                    'content',
                    'schedule date',
                    'schedule time',
                    'timezone',
                    'status',
                ];

                foreach ($required as $field) {

                    if (
                        ! array_key_exists($field, $post) ||
                        trim((string) $post[$field]) === ''
                    ) {
                        throw new \RuntimeException(
                            "AI generated row {$row}: {$field} is required."
                        );
                    }
                }

                /*
                * Project must exist.
                */
                if (
                    ! in_array(
                        mb_strtolower(trim($post['project'])),
                        $projectNames,
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        "AI generated row {$row}: project does not exist."
                    );
                }

                /*
                * Normalize platform.
                */
              $platform = strtolower(trim((string) ($post['platform'] ?? '')));

                if ($platform === 'x' || $platform === 'x (twitter)') {
                    $platform = 'twitter';
                }

                if (! in_array($platform, $allowedPlatforms, true)) {
                    throw new \RuntimeException(
                        "AI generated row {$row}: unsupported platform."
                    );
                }

                if (! collect($availableAccounts)->contains(fn (array $account) => $account['project'] === mb_strtolower(trim($post['project'])) && $account['platform'] === $platform && $account['account'] === mb_strtolower(trim($post['account/page'])))) {
                    throw new \RuntimeException("AI generated row {$row}: account/page is unavailable for the selected project and platform.");
                }

                /*
                * Media MUST remain empty.
                */
                $post['media url'] = '';

                /*
                * Instagram content type.
                */
                $contentType = strtolower(
                    trim((string) ($post['instagram content type'] ?? ''))
                );

                if ($platform === 'instagram') {

                    if (! in_array(
                        $contentType,
                        [
                            'image post',
                            'image',
                            'carousel',
                            'reel',
                        ],
                        true
                    )) {
                        throw new \RuntimeException(
                            "AI generated row {$row}: invalid Instagram Content Type."
                        );
                    }

                } elseif ($contentType !== '') {

                    throw new \RuntimeException(
                        "AI generated row {$row}: Instagram Content Type can only be used for Instagram."
                    );
                }

                /*
                * Content limits.
                */
                $limits = [
                    'instagram' => 2200,
                    'linkedin' => 3000,
                    'twitter' => 280,
                ];

                if (
                    isset($limits[$platform]) &&
                    mb_strlen($post['content']) > $limits[$platform]
                ) {
                    throw new \RuntimeException(
                        "AI generated row {$row}: content exceeds {$platform} character limit."
                    );
                }

                /*
                * Timezone.
                */
                if (! in_array($post['timezone'], ['Asia/Kolkata', 'UTC', 'America/New_York', 'Europe/London', 'Australia/Sydney'], true)) {
                    throw new \RuntimeException(
                        "AI generated row {$row}: invalid timezone."
                    );
                }

                if (! in_array(strtolower(trim($post['status'])), ['draft', 'pending'], true)) {
                    throw new \RuntimeException("AI generated row {$row}: invalid status.");
                }

                /*
                * Date/time.
                */
                try {

                    $scheduled = \Carbon\Carbon::createFromFormat(
                        '!Y-m-d H:i',
                        $post['schedule date'].' '.$post['schedule time'],
                        $post['timezone']
                    );

                } catch (\Throwable) {

                    throw new \RuntimeException(
                        "AI generated row {$row}: invalid schedule date/time."
                    );
                }

                if (
                    $scheduled->lessThanOrEqualTo(
                        now($post['timezone'])
                    )
                ) {
                    throw new \RuntimeException(
                        "AI generated row {$row}: schedule time is in the past."
                    );
                }
            }
        }
}
