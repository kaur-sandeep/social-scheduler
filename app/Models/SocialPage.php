<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialPage extends Model
{
    use SoftDeletes;

    protected $fillable = ['social_account_id','provider', 'page_id', 'page_name','category','profile_image','page_access_token','instagram_business_id','instagram_username','instagram_profile_image','permissions', 'status',];

    protected function casts(): array
    {
        return [
            'page_access_token' => 'encrypted',
            'permissions' => 'array',
        ];
    }

    public function account()
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
