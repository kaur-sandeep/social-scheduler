<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YouTubePostPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_privacy_is_stored_only_for_youtube_posts(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'YouTube']);

        $youtubePost = app(PostService::class)->create($user, [
            'project_id' => $project->id,
            'platform' => 'youtube',
            'youtube_privacy' => 'unlisted',
            'message' => 'YouTube video',
            'action' => 'draft',
            'timezone' => 'UTC',
        ]);
        $facebookPost = app(PostService::class)->create($user, [
            'project_id' => $project->id,
            'platform' => 'facebook',
            'youtube_privacy' => 'public',
            'message' => 'Facebook post',
            'action' => 'draft',
            'timezone' => 'UTC',
        ]);

        $this->assertSame('unlisted', $youtubePost->youtube_privacy);
        $this->assertNull($facebookPost->youtube_privacy);
    }
}
