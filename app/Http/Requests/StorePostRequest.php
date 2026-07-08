<?php

namespace App\Http\Requests;

use App\Enums\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::enum(SocialProvider::class)],
            'social_page_id' => ['nullable', 'exists:social_pages,id'],
            'message' => ['required', 'string', 'max:63206'],
            'scheduled_date' => ['nullable', 'date', 'required_if:action,schedule'],
            'scheduled_time' => ['nullable', 'date_format:H:i', 'required_if:action,schedule'],
            'timezone' => ['required', 'timezone'],
            'action' => ['required', Rule::in(['draft', 'schedule'])],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm', 'max:51200'],
        ];
    }
}
