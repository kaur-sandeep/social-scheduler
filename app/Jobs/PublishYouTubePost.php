<?php
namespace App\Jobs;
class PublishYouTubePost extends PublishSocialPost { protected function service(): string { return \App\Services\Social\YouTubeService::class; } protected function provider(): string { return 'youtube'; } }
