<?php
namespace App\Jobs;
class PublishTwitterPost extends PublishSocialPost { protected function service(): string { return \App\Services\Social\TwitterService::class; } protected function provider(): string { return 'twitter'; } }
