<?php
namespace App\Jobs;
class PublishInstagramPost extends PublishSocialPost { protected function service(): string { return \App\Services\Social\InstagramService::class; } protected function provider(): string { return 'instagram'; } }
