<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedPost extends Model
{
    protected $fillable = [
        'post_id', 'platform', 'error_message', 'context', 'retry_count', 'next_retry_at', 'resolved',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'next_retry_at' => 'datetime',
            'resolved' => 'boolean',
        ];
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
