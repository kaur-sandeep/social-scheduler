<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostImportError extends Model
{
    protected $fillable = ['post_import_id', 'row_number', 'project', 'platform', 'account', 'error_message'];
    public function postImport() { return $this->belongsTo(PostImport::class); }
}
