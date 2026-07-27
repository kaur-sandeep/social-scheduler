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
            $isScheduled = ($data['action'] ?? 'draft') === 'schedule';
            $scheduledAt = $isScheduled ? $this->scheduledAt($data) : null;

            $post = Post::query()->create([
                'project_id' => $data['project_id'],
                'user_id' => $user->id,
                'social_page_id' => $data['social_page_id'] ?? null,
                'platform' => $data['platform'],
                'message' => $data['message'],
                'status' => $status,
                'scheduled_date' => $isScheduled ? ($data['scheduled_date'] ?? null) : null,
                'scheduled_time' => $isScheduled ? ($data['scheduled_time'] ?? null) : null,
                'scheduled_at' => $scheduledAt,
                'timezone' => $data['timezone'] ?? $user->timezone ?? config('app.timezone'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->mediaService->attachUploads($post, $data['media'] ?? []);

            return $post->refresh();
        });
    }

    /** @return \Illuminate\Support\Collection<int, Post> */
    public function createMany(User $user, array $data)
    {
        return DB::transaction(function () use ($user, $data) {
            return collect($data['publishes'] ?? [])->map(function (array $publish) use ($user, $data) {
                return $this->create($user, array_merge($data, $publish));
            });
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
            $isScheduled = ($data['action'] ?? 'draft') === 'schedule';

            $post->update([
                'project_id' => $data['project_id'], 'social_page_id' => $data['social_page_id'] ?? null,
                'platform' => $data['platform'], 'message' => $data['message'], 'status' => $status,
                'scheduled_date' => $isScheduled ? ($data['scheduled_date'] ?? null) : null,
                'scheduled_time' => $isScheduled ? ($data['scheduled_time'] ?? null) : null,
                'scheduled_at' => $isScheduled ? $this->scheduledAt($data) : null, 'timezone' => $data['timezone'],
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
