<?php

namespace App\Services;

use App\Enums\SocialProvider;
use App\Models\Post;
use App\Models\PostImport;
use App\Models\Project;
use App\Models\SocialPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostImportService
{
    private const HEADERS = ['project', 'platform', 'account/page', 'content', 'media url', 'schedule date', 'schedule time', 'timezone', 'status'];

    public function processRow(PostImport $import, int $rowNumber, array $row): void
    {
        $row = array_combine(self::HEADERS, array_pad(array_map(fn ($value) => trim((string) $value), array_values($row)), count(self::HEADERS), ''));
        try {
            $data = $this->validateRow($import->user, $row);
            if (Post::query()->where('user_id', $import->user_id)->where('project_id', $data['project_id'])->where('platform', $data['platform'])->where('message', $data['message'])->where('scheduled_at', $data['scheduled_at'])->exists()) {
                $this->error($import, $rowNumber, $row, 'Duplicate post skipped.');
                $import->increment('skipped_rows');
                return;
            }
            $file = $this->downloadMedia($row['media url']);
            app(PostService::class)->create($import->user, $data + ['media' => [$file]]);
            $import->increment('successful_rows');
        } catch (\Throwable $e) {
            Log::warning('Post import row failed', ['import_id' => $import->id, 'row' => $rowNumber, 'error' => $e->getMessage()]);
            $this->error($import, $rowNumber, $row ?? [], $e->getMessage());
            $import->increment('failed_rows');
        } finally {
            $import->increment('processed_rows');
        }
    }

    private function validateRow(User $user, array $row): array
    {
        foreach (self::HEADERS as $required) if ($row[$required] === '') throw new \InvalidArgumentException(ucwords($required).' is required.');
        $project = Project::query()->where('user_id', $user->id)->where('name', $row['project'])->first();
        if (! $project) throw new \InvalidArgumentException('Project does not exist or is not available to you.');
        $platform = strtolower($row['platform']) === 'x (twitter)' || strtolower($row['platform']) === 'x' ? 'twitter' : strtolower($row['platform']);
        if (! in_array($platform, array_column(SocialProvider::cases(), 'value'), true)) throw new \InvalidArgumentException('Platform is not supported.');
        $page = SocialPage::query()->whereHas('account', fn ($q) => $q->where('user_id', $user->id)->where('project_id', $project->id)->where('status', 'active'))->where(fn ($q) => $q->where('page_name', $row['account/page'])->orWhere('instagram_username', ltrim($row['account/page'], '@')))->first();
        if (! $page || ($page->provider !== $platform && ! ($platform === 'instagram' && $page->instagram_business_id))) throw new \InvalidArgumentException('Connected account/page is unavailable for this platform.');
        $limits = ['instagram' => 2200, 'linkedin' => 3000, 'twitter' => 280];
        if (mb_strlen($row['content']) > ($limits[$platform] ?? 63206)) throw new \InvalidArgumentException('Content exceeds the platform character limit.');
        if (! in_array($row['timezone'], timezone_identifiers_list(), true)) throw new \InvalidArgumentException('Invalid timezone.');
        try { $scheduled = Carbon::createFromFormat('!Y-m-d H:i', $row['schedule date'].' '.$row['schedule time'], $row['timezone']); } catch (\Throwable) { throw new \InvalidArgumentException('Schedule Date must be YYYY-MM-DD and Schedule Time must be HH:MM.'); }
        if ($scheduled->lessThanOrEqualTo(now($row['timezone']))) throw new \InvalidArgumentException('Schedule time is in the past.');
        if (! filter_var($row['media url'], FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($row['media url'], PHP_URL_HOST)), ['dropbox.com', 'www.dropbox.com', 'dl.dropboxusercontent.com'], true)) throw new \InvalidArgumentException('Media URL must be a valid Dropbox URL.');
        return ['project_id' => $project->id, 'social_page_id' => $page->id, 'platform' => $platform, 'message' => $row['content'], 'scheduled_date' => $scheduled->toDateString(), 'scheduled_time' => $scheduled->format('H:i'), 'scheduled_at' => $scheduled->utc(), 'timezone' => $row['timezone'], 'action' => strtolower($row['status']) === 'draft' ? 'draft' : 'schedule'];
    }

    private function downloadMedia(string $url): UploadedFile
    {
        $url = preg_replace('/([?&])dl=0(&|$)/', '$1raw=1$2', $url) ?: $url;
        $response = Http::timeout(90)->retry(2, 1000)->get($url);
        if (! $response->successful()) throw new \RuntimeException('Media download failed (HTTP '.$response->status().').');
        $mime = strtolower(explode(';', $response->header('Content-Type', ''))[0]);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/x-msvideo' => 'avi'];
        if (! isset($allowed[$mime])) throw new \RuntimeException('Downloaded media type is not supported.');
        $path = tempnam(sys_get_temp_dir(), 'post-import-');
        file_put_contents($path, $response->body());
        return new UploadedFile($path, 'imported.'.$allowed[$mime], $mime, null, true);
    }

    private function error(PostImport $import, int $row, array $data, string $message): void
    {
        $import->errors()->create(['row_number' => $row, 'project' => $data['project'] ?? null, 'platform' => $data['platform'] ?? null, 'account' => $data['account/page'] ?? null, 'error_message' => $message]);
    }
}
