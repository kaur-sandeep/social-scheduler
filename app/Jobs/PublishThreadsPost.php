<?php
namespace App\Jobs;
class PublishThreadsPost extends PublishSocialPost { protected function service(): string { return \App\Services\Social\ThreadsService::class; } protected function provider(): string { return 'threads'; } }
