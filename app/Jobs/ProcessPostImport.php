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
            $sheet = IOFactory::load(storage_path('app/private/'.$import->file_path))->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
            if ($headers !== ['project','platform','account/page','content','media url','schedule date','schedule time','timezone','status']) throw new \RuntimeException('The spreadsheet headings do not match the sample template.');
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
