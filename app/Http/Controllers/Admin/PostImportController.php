<?php

namespace App\Http\Controllers\Admin;

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

class PostImportController extends Controller
{
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
        $book = new Spreadsheet(); $sheet = $book->getActiveSheet(); $sheet->setTitle('Posts');
        $headings = ['Project', 'Platform', 'Account/Page', 'Content', 'Media URL', 'Schedule Date', 'Schedule Time', 'Timezone', 'Status'];
        $sheet->fromArray($headings, null, 'A1'); $sheet->freezePane('A2'); $sheet->getStyle('A1:I1')->getFont()->setBold(true); $sheet->getStyle('A1:I1')->getFill()->setFillType('solid')->getStartColor()->setRGB('D9EAF7');
        foreach (range('A', 'I') as $column) $sheet->getColumnDimension($column)->setWidth($column === 'D' ? 48 : 22);
        // Let Excel accept normal date/time entry while consistently displaying the values expected by the importer.
        $sheet->getStyle('F2:F10000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('G2:G10000')->getNumberFormat()->setFormatCode('hh:mm');
        $list = $book->createSheet(); $list->setTitle('Lists'); $list->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $projectNames = $projects->projectsFor($request->user())->pluck('name')->values()->all();
        $accounts = SocialPage::query()->whereHas('account', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))->orderBy('provider')->orderBy('page_name')->get()->map(fn ($p) => $p->provider === 'instagram' && $p->instagram_username ? '@'.$p->instagram_username : $p->page_name)->unique()->values()->all();
        $lists = [$projectNames, ['Facebook','Instagram','LinkedIn','X (Twitter)','Pinterest','Threads','YouTube'], $accounts, ['Asia/Kolkata','UTC','America/New_York','Europe/London','Australia/Sydney'], ['Draft','Pending']];
        foreach ($lists as $column => $values) foreach ($values as $row => $value) $list->setCellValueByColumnAndRow($column + 1, $row + 1, $value);
        foreach (['A' => 'A', 'B' => 'B', 'C' => 'C', 'H' => 'D', 'I' => 'E'] as $target => $source) { $validation = new DataValidation(); $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(false)->setShowDropDown(true)->setFormula1("Lists!\${$source}\$1:\${$source}\$".max(1, count($lists[array_search($target, ['A','B','C','H','I'])]))); $sheet->setDataValidation("{$target}2:{$target}10000", $validation); }
        $sheet->setCellValue('G2', '09:00'); $sheet->setCellValue('H2', 'Asia/Kolkata'); $sheet->setCellValue('I2', 'Pending');
        return response()->streamDownload(function () use ($book) { (new Xlsx($book))->save('php://output'); }, 'social-post-import-template.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
