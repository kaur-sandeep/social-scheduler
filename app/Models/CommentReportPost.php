<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentReportPost extends Model
{
    protected $fillable = ['position', 'comment_text', 'post_url'];
}
