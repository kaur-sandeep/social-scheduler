<?php

namespace App\Jobs;

use App\Models\PostImport;
use App\Notifications\PostImportCompletedNotification;
use App\Services\PostImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessPostImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $timeout = 3600;
    public int $tries = 2;
    public function __construct(public PostImport $import) { $this->onQueue('imports'); }
    public function handle(PostImportService $service): void
    {
        $import = $this->import->fresh();
        $import->update(['status' => 'processing', 'started_at' => now()]);
        Log::info('Post import started', ['import_id' => $import->id, 'user_id' => $import->user_id]);
        try {
            $workbook = IOFactory::load(storage_path('app/private/'.$import->file_path));
            $expectedHeaders = ['project','platform','instagram content type','account/page','content','media url','schedule date','schedule time','timezone','status'];
            $normalizeHeaders = static function (array $headers): array {
                $headers = array_map(function ($header): string {
                    $header = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], ['', ' '], (string) $header);

                    return strtolower(trim((string) preg_replace('/\s+/u', ' ', $header)));
                }, $headers);

                while ($headers !== [] && end($headers) === '') {
                    array_pop($headers);
                }

                return $headers;
            };

            // AI exports may include hidden worksheets for dropdown values. Locate
            // the worksheet by its template row rather than Excel's active tab.
            $sheet = null;
            foreach ($workbook->getWorksheetIterator() as $candidate) {
                $candidateHeaders = $normalizeHeaders(
                    $candidate->rangeToArray('A1:J1', null, true, true, false)[0]
                );
                if ($candidateHeaders === $expectedHeaders) {
                    $sheet = $candidate;
                    break;
                }
            }

            $sheet ??= $workbook->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $headers = $normalizeHeaders($rows[0] ?? []);
            if ($headers !== $expectedHeaders) {
                Log::warning('Post import template headings did not match.', [
                    'import_id' => $import->id,
                    'worksheet' => $sheet->getTitle(),
                    'headings' => $headers,
                ]);
                throw new \RuntimeException('The spreadsheet headings do not match the sample template.');
            }
            $dataRows = array_filter(array_slice($rows, 1), fn ($row) => (bool) array_filter($row, fn ($value) => $value !== null && $value !== ''));
            $import->update(['total_rows' => count($dataRows)]);
            foreach ($dataRows as $index => $row) {
                $service->processRow($import, $index + 2, $row);
            }
            $import->refresh()->update(['status' => 'completed', 'completed_at' => now()]);
            $import->user->notify(new PostImportCompletedNotification($import->fresh()));
            Log::info('Post import completed', ['import_id' => $import->id]);
        } catch (\Throwable $e) {
            report($e); $import->update(['status' => 'failed', 'failure_reason' => $e->getMessage(), 'completed_at' => now()]);
        }
    }
}
