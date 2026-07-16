<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Services\Social\Clients\ThreadsClient;
use App\Services\Social\Concerns\InteractsWithPosts;

class ThreadsService { use InteractsWithPosts; public function __construct(private readonly ThreadsClient $client) {} public function publish(Post $post): array { $page = $this->page($post, 'threads'); $media = $post->media->first(); $payload = ['media_type' => $media ? ($media->media_type === 'video' ? 'VIDEO' : 'IMAGE') : 'TEXT', 'text' => $post->message]; if ($media) $payload[$media->media_type === 'video' ? 'video_url' : 'image_url'] = $this->mediaUrl($media->path); $container = $this->client->createContainer($post, $page->page_id, $page->page_access_token, $payload); return $this->client->publishContainer($post, $page->page_id, $page->page_access_token, $container['id']); } }
