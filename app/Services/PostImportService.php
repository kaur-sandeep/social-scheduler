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
    private const HEADERS = ['project', 'platform', 'instagram content type', 'account/page', 'content', 'media url', 'schedule date', 'schedule time', 'timezone', 'status'];

    public function processRow(PostImport $import, int $rowNumber, array $row): void
    {
        $row = array_combine(self::HEADERS, array_pad(array_map(fn ($value) => trim((string) $value), array_values($row)), count(self::HEADERS), ''));
        try {
            $data = $this->validateRow($import->user, $row);
            $files = $this->downloadMedia($row['media url']);
            $this->validateMediaForPlatform($data['platform'], $data['content_type'], $files);
            if (Post::query()->where('user_id', $import->user_id)->where('project_id', $data['project_id'])->where('platform', $data['platform'])->where('message', $data['message'])->where('scheduled_at', $data['scheduled_at'])->exists()) {
                $this->error($import, $rowNumber, $row, 'Duplicate post skipped.');
                $import->increment('skipped_rows');
                return;
            }
            app(PostService::class)->create($import->user, $data + ['media' => $files]);
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
        foreach (self::HEADERS as $required) if (! in_array($required, ['media url', 'instagram content type'], true) && $row[$required] === '') throw new \InvalidArgumentException(ucwords($required).' is required.');
        $project = Project::query()->where('user_id', $user->id)->where('name', $row['project'])->first();
        if (! $project) throw new \InvalidArgumentException('Project does not exist or is not available to you.');
        $platform = strtolower($row['platform']) === 'x (twitter)' || strtolower($row['platform']) === 'x' ? 'twitter' : strtolower($row['platform']);
        if (! in_array($platform, array_column(SocialProvider::cases(), 'value'), true)) throw new \InvalidArgumentException('Platform is not supported.');
        $contentType = match (strtolower($row['instagram content type'])) {
            'image post', 'image' => 'image',
            'carousel' => 'carousel',
            'reel' => 'reel',
            '' => null,
            default => throw new \InvalidArgumentException('Instagram Content Type must be Image Post, Carousel, or Reel.'),
        };
        if ($platform === 'instagram' && ! $contentType) throw new \InvalidArgumentException('Instagram Content Type is required: select Image Post, Carousel, or Reel.');
        if ($platform !== 'instagram' && $contentType) throw new \InvalidArgumentException('Instagram Content Type can only be used when Platform is Instagram.');
        $accountPage = preg_replace('/^(?:Facebook|Instagram|LinkedIn|X \(Twitter\)|TikTok|Pinterest|Threads|YouTube) (?:—|\x{00E2}\x{20AC}\x{201D}) /u', '', $row['account/page']) ?? $row['account/page'];
        $accountPage = $this->accountName($accountPage);
        $page = SocialPage::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id)->where('project_id', $project->id)->where('status', 'active'))
            ->get()
            ->first(fn (SocialPage $page) => $this->accountName($page->page_name) === $accountPage
                || $this->accountName('@'.($page->instagram_username ?? '')) === $accountPage);
        if (! $page || ($page->provider !== $platform && ! ($platform === 'instagram' && $page->instagram_business_id))) throw new \InvalidArgumentException('Connected account/page is unavailable for this platform.');
        $limits = ['instagram' => 2200, 'linkedin' => 3000, 'twitter' => 280];
        if (mb_strlen($row['content']) > ($limits[$platform] ?? 63206)) throw new \InvalidArgumentException('Content exceeds the platform character limit.');
        if (! in_array($row['timezone'], timezone_identifiers_list(), true)) throw new \InvalidArgumentException('Invalid timezone.');
        try { $scheduled = Carbon::createFromFormat('!Y-m-d H:i', $row['schedule date'].' '.$row['schedule time'], $row['timezone']); } catch (\Throwable) { throw new \InvalidArgumentException('Schedule Date must be YYYY-MM-DD and Schedule Time must be HH:MM.'); }
        if ($scheduled->lessThanOrEqualTo(now($row['timezone']))) throw new \InvalidArgumentException('Schedule time is in the past.');
        foreach ($this->mediaUrls($row['media url']) as $url) if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['dropbox.com', 'www.dropbox.com', 'dl.dropboxusercontent.com'], true)) throw new \InvalidArgumentException('Each Media URL must be a valid Dropbox URL.');
        return ['project_id' => $project->id, 'social_page_id' => $page->id, 'platform' => $platform, 'content_type' => $contentType, 'message' => $row['content'], 'scheduled_date' => $scheduled->toDateString(), 'scheduled_time' => $scheduled->format('H:i'), 'scheduled_at' => $scheduled->utc(), 'timezone' => $row['timezone'], 'action' => strtolower($row['status']) === 'draft' ? 'draft' : 'schedule'];
    }

    private function accountName(?string $label): string
    {
        $label = str_replace(
            ["\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D", "\xC2\xA0"],
            ["\xE2\x80\x94", ' '],
            (string) $label
        );
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        $withoutPlatform = preg_replace(
            '/^(?:Facebook|Instagram|LinkedIn|X \(Twitter\)|TikTok|Pinterest|Threads|YouTube)\s+—\s+/iu',
            '',
            $label
        );

        return mb_strtolower(trim($withoutPlatform ?? $label));
    }

    private function downloadMedia(string $urls): array
    {
        return array_map(fn (string $url) => $this->downloadMediaFile($url), $this->mediaUrls($urls));
    }

    private function downloadMediaFile(string $url): UploadedFile
    {
        $url = preg_replace('/([?&])dl=0(&|$)/', '$1raw=1$2', $url) ?: $url;
        $response = Http::timeout(90)->retry(2, 1000)->get($url);
        if (! $response->successful()) throw new \RuntimeException('Media download failed (HTTP '.$response->status().').');
        $mime = strtolower(explode(';', $response->header('Content-Type', ''))[0]);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/x-msvideo' => 'avi', 'video/webm' => 'webm'];
        if (! isset($allowed[$mime])) throw new \RuntimeException('Downloaded media type is not supported.');
        $path = tempnam(sys_get_temp_dir(), 'post-import-');
        file_put_contents($path, $response->body());
        return new UploadedFile($path, 'imported.'.$allowed[$mime], $mime, null, true);
    }

    private function mediaUrls(string $urls): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $urls)), fn (string $url) => $url !== ''));
    }

    private function validateMediaForPlatform(string $platform, ?string $contentType, array $files): void
    {
        $mimes = array_map(fn (UploadedFile $file) => $file->getMimeType(), $files);
        $isImage = fn (string $mime): bool => in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
        $isVideo = fn (string $mime): bool => in_array($mime, ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'], true);

        if ($platform === 'twitter' && $files) throw new \InvalidArgumentException('X posts imported through this scheduler must not include Media URL because X media uploads are not enabled.');
        if ($platform === 'instagram' && $contentType === 'image' && (count($mimes) !== 1 || ! in_array($mimes[0], ['image/jpeg', 'image/png'], true))) throw new \InvalidArgumentException('An Instagram Image Post requires exactly one JPEG or PNG image.');
        if ($platform === 'instagram' && $contentType === 'reel' && (count($mimes) !== 1 || ! in_array($mimes[0], ['video/mp4', 'video/quicktime'], true))) throw new \InvalidArgumentException('An Instagram Reel requires exactly one MP4 or MOV video.');
        if ($platform === 'instagram' && $contentType === 'carousel' && (count($mimes) < 2 || count($mimes) > 10 || collect($mimes)->contains(fn (string $mime) => ! in_array($mime, ['image/jpeg', 'image/png', 'video/mp4', 'video/quicktime'], true)))) throw new \InvalidArgumentException('An Instagram Carousel requires 2 to 10 JPEG, PNG, MP4, or MOV files. Separate Media URLs with |.');
        if ($platform === 'linkedin' && ($files && (count($mimes) !== 1 || ! in_array($mimes[0], ['image/jpeg', 'image/png', 'image/gif'], true)))) throw new \InvalidArgumentException('LinkedIn supports one JPEG, PNG, or GIF image only; video and multi-image uploads are not supported.');
        if ($platform === 'tiktok' && (count($mimes) !== 1 || ! in_array($mimes[0], ['video/mp4', 'video/quicktime', 'video/webm'], true))) throw new \InvalidArgumentException('TikTok requires exactly one MP4, MOV, or WebM video.');
        if ($platform === 'youtube' && (count($mimes) !== 1 || ! $isVideo($mimes[0]))) throw new \InvalidArgumentException('YouTube requires exactly one video.');
        if ($platform === 'pinterest' && (count($mimes) !== 1 || ! $isImage($mimes[0]))) throw new \InvalidArgumentException('Pinterest requires exactly one image.');
    }

    private function error(PostImport $import, int $row, array $data, string $message): void
    {
        $import->errors()->create(['row_number' => $row, 'project' => $data['project'] ?? null, 'platform' => $data['platform'] ?? null, 'account' => $data['account/page'] ?? null, 'error_message' => $message]);
    }
}
