<?php

namespace App\Http\Requests;

use App\Enums\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\SocialPage;
use App\Models\Project;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail): void { if (! Project::where('id', $value)->where('user_id', $this->user()->id)->exists()) $fail('Choose one of your projects.'); }],
            'platform' => ['required', Rule::enum(SocialProvider::class)],
            'social_page_id' => ['nullable', 'exists:social_pages,id', 'required_if:action,schedule', 'required_if:action,publish', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value) return;
                $page = SocialPage::query()->whereKey($value)->whereHas('account', fn ($query) => $query->where('user_id', $this->user()->id)->where('project_id', $this->input('project_id')))->first();
                if (! $page) { $fail('Choose a destination connected to your account.'); return; }
                $platform = $this->input('platform');
                if ($page->provider !== $platform && ! ($platform === 'instagram' && $page->provider === 'facebook' && $page->instagram_business_id)) $fail('The selected destination does not support this platform.');
            }],
            'message' => ['required', 'string', 'max:63206', function (string $attribute, mixed $value, \Closure $fail): void {
                $limit = match ($this->input('platform')) { 'instagram' => 2200, 'linkedin' => 3000, 'twitter' => 280, default => 63206 };
                if (mb_strlen($value) > $limit) $fail("The caption exceeds the {$limit}-character limit for the selected platform.");
            }],
            'scheduled_date' => ['nullable', 'date', 'required_if:action,schedule'],
            'scheduled_time' => ['nullable', 'date_format:H:i', 'required_if:action,schedule'],
            'timezone' => ['required', 'timezone'],
            'action' => ['required', Rule::in(['draft', 'schedule', 'publish'])],
            'media' => ['nullable', 'array', 'max:'.config('social.max_media_files')],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm', 'max:'.config('social.max_upload_kilobytes')],
        ];
    }
}
