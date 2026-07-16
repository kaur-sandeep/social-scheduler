<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Services\Social\Clients\InstagramClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Collection;
use RuntimeException;

class InstagramService
{
    use InteractsWithPosts;

    public function __construct(private readonly InstagramClient $client) {}

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'facebook');
        $instagramId = $page->instagram_business_id;
        if (! $instagramId) throw new RuntimeException('Connect an Instagram professional account to this Facebook page first.');
        $items = $post->media;
        if ($items->isEmpty()) throw new RuntimeException('Instagram publishing requires an image or video.');

        $this->validateMedia($items);

        if ($items->count() === 1) {
            $container = $this->client->createContainer(
                $post,
                $instagramId,
                $page->page_access_token,
                $this->containerPayload($items->first(), false, $post->message)
            );

            $this->waitUntilReady($post, $container['id'], $page->page_access_token);

            return $this->client->publishContainer($post, $instagramId, $page->page_access_token, $container['id']);
        }

        $children = $items->map(function ($media) use ($post, $page, $instagramId): string {
            $container = $this->client->createContainer(
                $post,
                $instagramId,
                $page->page_access_token,
                $this->containerPayload($media, true)
            );
            $this->waitUntilReady($post, $container['id'], $page->page_access_token);

            return $container['id'];
        })->all();

        $parent = $this->client->createContainer($post, $instagramId, $page->page_access_token, [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $children),
            'caption' => $post->message,
        ]);

        $this->waitUntilReady($post, $parent['id'], $page->page_access_token);

        return $this->client->publishContainer($post, $instagramId, $page->page_access_token, $parent['id']);
    }

    private function containerPayload(object $media, bool $isCarouselItem, ?string $caption = null): array
    {
        $payload = ['is_carousel_item' => $isCarouselItem];

        if ($media->media_type === 'video') {
            $payload += ['media_type' => $isCarouselItem ? 'VIDEO' : 'REELS', 'video_url' => $this->mediaUrl($media->path)];
        } else {
            $payload += ['media_type' => 'IMAGE', 'image_url' => $this->mediaUrl($media->path)];
        }

        if ($caption !== null) $payload['caption'] = $caption;

        return $payload;
    }

    private function validateMedia(Collection $items): void
    {
        if ($items->count() > 10) throw new RuntimeException('Instagram supports a maximum of 10 carousel items.');
        foreach ($items as $media) {
            if ($media->media_type === 'image' && ! in_array($media->mime_type, ['image/jpeg', 'image/png'], true)) {
                throw new RuntimeException('Instagram accepts JPEG or PNG images. Convert this media before publishing.');
            }
            if ($media->media_type === 'video' && ! in_array($media->mime_type, ['video/mp4', 'video/quicktime'], true)) {
                throw new RuntimeException('Instagram accepts MP4 or MOV videos. Convert this media before publishing.');
            }
        }
    }

    private function waitUntilReady(Post $post, string $containerId, string $token): void
    {
        for ($attempt = 0; $attempt < 24; $attempt++) {
            $status = $this->client->containerStatus($post, $containerId, $token);
            $code = data_get($status, 'status_code');
            if ($code === 'FINISHED') return;
            if (in_array($code, ['ERROR', 'EXPIRED'], true)) throw new RuntimeException(data_get($status, 'status', 'Instagram media processing failed.'));
            sleep(5);
        }
        throw new RuntimeException('Instagram media processing timed out.');
    }
}
