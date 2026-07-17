<?php

namespace App\Repositories;

use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PostRepository
{
    public function deletedForUser(int $userId): Collection
    {
        return Post::onlyTrashed()->where('user_id', $userId)->with(['socialPage', 'media'])->latest('deleted_at')->get();
    }
    public function paginateForUser(int $userId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Post::query()
            ->with(['socialPage', 'media'])
            ->where('user_id', $userId)
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->where('platform', $platform))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('scheduled_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function due(CarbonInterface $now, int $limit = 250): Collection
    {
        return Post::query()
            ->with(['socialPage', 'media', 'user'])
            ->where('status', PostStatus::Pending)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->lockForUpdate()
            ->get();
    }

    public function calendarEvents(int $userId, array $filters = []): Collection
    {
        return Post::query()
            ->with(['socialPage', 'media'])
            ->where('user_id', $userId)
            ->when($filters['start'] ?? null, fn ($query, $start) => $query->where('scheduled_at', '>=', $start))
            ->when($filters['end'] ?? null, fn ($query, $end) => $query->where('scheduled_at', '<=', $end))
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->where('platform', $platform))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('scheduled_at')
            ->get();
    }
}
