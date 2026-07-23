<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostImportRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array { return ['import_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']]; }
}
