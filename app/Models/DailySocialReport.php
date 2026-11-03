<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySocialReport extends Model
{
    protected $fillable = ['report_date', 'status', 'sent_at', 'failure_message'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'sent_at' => 'datetime'];
    }
}
