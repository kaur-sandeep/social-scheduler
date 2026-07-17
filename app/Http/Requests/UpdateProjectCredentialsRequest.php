<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectCredentialsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->id === $this->route('project')->user_id; }
    public function rules(): array { return ['credentials' => ['required', 'array'], 'credentials.*.client_id' => ['nullable', 'string', 'max:255', 'required_with:credentials.*.client_secret'], 'credentials.*.client_secret' => ['nullable', 'string'], 'credentials.*.redirect_uri' => ['nullable', 'url', 'max:255'], 'credentials.*.status' => ['required', Rule::in(['active', 'inactive'])]]; }
}
