<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MovePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->route('post')?->user_id === auth()->id();
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
        ];
    }
}
