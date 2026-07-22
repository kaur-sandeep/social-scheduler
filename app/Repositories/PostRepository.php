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
            ->with(['socialPage', 'media', 'project'])
            ->where('user_id', $userId)
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->whereIn('platform', (array) $platform))
            ->when($filters['project'] ?? null, fn ($query, $project) => $query->whereIn('project_id', (array) $project))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereIn('status', (array) $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('scheduled_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('scheduled_at', '<=', $date))
            ->when($filters['q'] ?? null, function ($query, $search): void {
                $search = trim($search);

                $query->where(function ($query) use ($search): void {
                    $query->when(ctype_digit($search), fn ($query) => $query->orWhereKey((int) $search))
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('platform', 'like', "%{$search}%")
                        ->orWhereHas('socialPage', fn ($query) => $query->where('page_name', 'like', "%{$search}%"))
                        ->orWhereHas('project', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->tap(function ($query) use ($filters): void {
                $columns = ['id' => 'id', 'scheduled' => 'scheduled_at', 'platform' => 'platform', 'project' => 'project_id', 'page' => 'social_page_id', 'status' => 'status', 'published' => 'published_at'];
                $column = $columns[$filters['sort'] ?? 'id'] ?? 'id';
                $direction = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
                $query->orderBy($column, $direction)->orderByDesc('id');
            })
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
            ->with(['socialPage', 'media', 'project'])
            ->where('user_id', $userId)
            ->when($filters['start'] ?? null, fn ($query, $start) => $query->where('scheduled_at', '>=', $start))
            ->when($filters['end'] ?? null, fn ($query, $end) => $query->where('scheduled_at', '<=', $end))
            ->when($filters['platform'] ?? null, fn ($query, $platform) => $query->where('platform', $platform))
            ->when($filters['project'] ?? null, fn ($query, $project) => $query->where('project_id', $project))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('scheduled_at')
            ->get();
    }
}
