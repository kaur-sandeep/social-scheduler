<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostLog extends Model
{
    protected $fillable = [
        'post_id', 'platform', 'endpoint', 'api_request', 'api_response', 'status_code',
        'execution_time_ms', 'success', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'api_request' => 'array',
            'api_response' => 'array',
            'success' => 'boolean',
        ];
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
