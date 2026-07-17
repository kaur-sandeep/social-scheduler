<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function __construct(private readonly MediaService $mediaService)
    {
    }

    public function create(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data) {
            $status = match ($data['action'] ?? 'draft') {
                'schedule' => PostStatus::Pending,
                'publish' => PostStatus::Queued,
                default => PostStatus::Draft,
            };
            $scheduledAt = $this->scheduledAt($data);

            $post = Post::query()->create([
                'project_id' => $data['project_id'],
                'user_id' => $user->id,
                'social_page_id' => $data['social_page_id'] ?? null,
                'platform' => $data['platform'],
                'message' => $data['message'],
                'status' => $status,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'scheduled_time' => $data['scheduled_time'] ?? null,
                'scheduled_at' => $scheduledAt,
                'timezone' => $data['timezone'] ?? $user->timezone ?? config('app.timezone'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->mediaService->attachUploads($post, $data['media'] ?? []);

            return $post->refresh();
        });
    }

    public function move(Post $post, string $scheduledAt, string $timezone): Post
    {
        $date = Carbon::parse($scheduledAt, $timezone);

        $post->update([
            'scheduled_date' => $date->toDateString(),
            'scheduled_time' => $date->format('H:i:s'),
            'scheduled_at' => $date->clone()->utc(),
            'timezone' => $timezone,
            'status' => PostStatus::Pending,
        ]);

        return $post->refresh();
    }

    public function update(Post $post, array $data): Post
    {
        return DB::transaction(function () use ($post, $data) {
            $status = match ($data['action'] ?? 'draft') {
                'schedule' => PostStatus::Pending,
                'publish' => PostStatus::Queued,
                default => PostStatus::Draft,
            };

            $post->update([
                'project_id' => $data['project_id'], 'social_page_id' => $data['social_page_id'] ?? null,
                'platform' => $data['platform'], 'message' => $data['message'], 'status' => $status,
                'scheduled_date' => $data['scheduled_date'] ?? null, 'scheduled_time' => $data['scheduled_time'] ?? null,
                'scheduled_at' => $this->scheduledAt($data), 'timezone' => $data['timezone'],
                'updated_by' => $post->user_id,
            ]);
            $this->mediaService->attachUploads($post, $data['media'] ?? []);

            return $post->refresh();
        });
    }

    private function scheduledAt(array $data): ?Carbon
    {
        if (empty($data['scheduled_date']) || empty($data['scheduled_time'])) {
            return null;
        }

        return Carbon::parse(
            $data['scheduled_date'].' '.$data['scheduled_time'],
            $data['timezone'] ?? config('app.timezone')
        )->utc();
    }
}
