<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function attachUploads(Post $post, array $uploads): void
    {
        foreach (array_values($uploads) as $index => $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $type = str_starts_with((string) $upload->getMimeType(), 'video/') ? 'video' : 'image';
            $path = $upload->storeAs(
                'posts/'.$post->id,
                Str::uuid().'.'.$upload->getClientOriginalExtension(),
                'public'
            );

            $post->media()->create([
                'media_type' => $type,
                'path' => $path,
                'thumbnail_path' => $type === 'image' ? $path : null,
                'mime_type' => (string) $upload->getMimeType(),
                'file_size' => $upload->getSize() ?: 0,
                'display_order' => $index,
            ]);
        }
    }

    public function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
