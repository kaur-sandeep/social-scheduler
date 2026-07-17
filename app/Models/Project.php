<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;
    protected $fillable = ['user_id', 'name'];

    public function user() { return $this->belongsTo(User::class); }
    public function socialAppCredentials() { return $this->hasMany(SocialAppCredential::class); }
    public function socialAccounts() { return $this->hasMany(SocialAccount::class); }
    public function posts() { return $this->hasMany(Post::class); }
}
