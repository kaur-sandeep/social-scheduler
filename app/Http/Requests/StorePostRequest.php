<?php

namespace App\Http\Requests;

use App\Enums\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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
            'social_page_id' => ['required', 'exists:social_pages,id', function (string $attribute, mixed $value, \Closure $fail): void {
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
            'content_type' => ['nullable', 'required_if:platform,instagram', Rule::in(['image', 'carousel', 'reel'])],
            'media' => ['nullable', 'array', 'max:'.config('social.max_media_files')],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,avi,webm', 'max:'.config('social.max_upload_kilobytes')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('platform') === 'instagram' && ! $validator->errors()->has('content_type')) {
                $media = $this->file('media', []);
                $contentType = $this->input('content_type');
                $isImage = fn ($file): bool => in_array($file->getMimeType(), ['image/jpeg', 'image/png'], true);
                $isVideo = fn ($file): bool => in_array($file->getMimeType(), ['video/mp4', 'video/quicktime'], true);

                if ($contentType === 'image' && (count($media) !== 1 || ! $isImage($media[0]))) {
                    $validator->errors()->add('media', 'An Instagram image post requires exactly one JPEG or PNG image.');
                }

                if ($contentType === 'reel' && (count($media) !== 1 || ! $isVideo($media[0]))) {
                    $validator->errors()->add('media', 'An Instagram reel requires exactly one MP4 or MOV video.');
                }

                if ($contentType === 'carousel' && (count($media) < 2 || count($media) > 10 || collect($media)->contains(fn ($file) => ! $isImage($file) && ! $isVideo($file)))) {
                    $validator->errors()->add('media', 'An Instagram carousel requires 2 to 10 JPEG, PNG, MP4, or MOV files.');
                }
            }

            if ($this->input('platform') === 'tiktok') {
                $media = $this->file('media', []);
                $isTikTokVideo = fn ($file): bool => in_array($file->getMimeType(), ['video/mp4', 'video/quicktime', 'video/webm'], true);

                if (count($media) !== 1 || ! $isTikTokVideo($media[0])) {
                    $validator->errors()->add('media', 'A TikTok post requires exactly one MP4, MOV, or WebM video.');
                }
            }

            if ($this->input('action') !== 'schedule' || $validator->errors()->hasAny(['scheduled_date', 'scheduled_time', 'timezone'])) {
                return;
            }

            try {
                $timezone = $this->input('timezone');
                $scheduledAt = Carbon::createFromFormat('!Y-m-d H:i', $this->input('scheduled_date').' '.$this->input('scheduled_time'), $timezone);

                if ($scheduledAt->lessThanOrEqualTo(now($timezone))) {
                    $validator->errors()->add('scheduled_time', 'You cannot schedule a post in the past. Please select a future date and time.');
                }
            } catch (\Throwable) {
                // The individual field rules provide the appropriate error message.
            }
        });
    }
}
