<?php
namespace App\Jobs;
class PublishLinkedInPost extends PublishSocialPost { protected function service(): string { return \App\Services\Social\LinkedInService::class; } protected function provider(): string { return 'linkedin'; } }
