<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'social_page_id', 'platform', 'message', 'status', 'scheduled_date',
        'scheduled_time', 'scheduled_at', 'timezone', 'published_at', 'provider_post_id',
        'error_message', 'retry_count', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'status' => PostStatus::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function socialPage()
    {
        return $this->belongsTo(SocialPage::class);
    }

    public function media()
    {
        return $this->hasMany(PostMedia::class)->orderBy('display_order');
    }

    public function logs()
    {
        return $this->hasMany(PostLog::class);
    }
}
