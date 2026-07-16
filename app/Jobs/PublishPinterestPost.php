<?php
namespace App\Jobs;
class PublishPinterestPost extends PublishSocialPost { protected function service(): string { return \App\Services\Social\PinterestService::class; } protected function provider(): string { return 'pinterest'; } }
