<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'provider', 'provider_user_id', 'provider_username', 'name', 'email',
        'user_access_token', 'refresh_token', 'token_expires_at', 'status',
        'connected_at', 'disconnected_at',
    ];

    protected function casts(): array
    {
        return [
            'user_access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pages()
    {
        return $this->hasMany(SocialPage::class);
    }
}
