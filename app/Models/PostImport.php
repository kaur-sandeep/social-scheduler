<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostImport extends Model
{
    protected $fillable = ['user_id', 'original_filename', 'file_path', 'status', 'total_rows', 'processed_rows', 'successful_rows', 'failed_rows', 'skipped_rows', 'started_at', 'completed_at', 'failure_reason'];
    protected function casts(): array { return ['started_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function errors() { return $this->hasMany(PostImportError::class); }
}
