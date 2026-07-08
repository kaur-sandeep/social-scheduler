<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'event', 'subject_type', 'subject_id', 'properties', 'ip_address'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }
}
