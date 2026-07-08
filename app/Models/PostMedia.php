<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMedia extends Model
{
    protected $fillable = [
        'post_id', 'media_type', 'path', 'thumbnail_path', 'mime_type', 'file_size', 'display_order',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
