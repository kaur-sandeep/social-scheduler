<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialAppCredential extends Model
{
    use SoftDeletes;
    protected $fillable = ['project_id', 'provider', 'client_id', 'client_secret', 'redirect_uri', 'additional_settings', 'status'];
    protected $hidden = ['client_secret'];
    protected function casts(): array { return ['client_secret' => 'encrypted', 'additional_settings' => 'array']; }
    public function project() { return $this->belongsTo(Project::class); }
    public function isUsable(): bool { return $this->status === 'active' && filled($this->client_id) && filled($this->client_secret); }
}
