<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommentReport extends Model
{
    protected $fillable = ['user_id', 'project_name', 'account_type', 'report_date', 'number_of_comments'];

    protected function casts(): array
    {
        return ['report_date' => 'date'];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CommentReportPost::class)->orderBy('position');
    }
}
