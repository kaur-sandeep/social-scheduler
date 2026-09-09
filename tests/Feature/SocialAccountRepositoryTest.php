<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\User;
use App\Repositories\SocialAccountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialAccountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_page_restores_a_soft_deleted_page_with_the_same_identity(): void
    {
        $user = User::factory()->create();
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'youtube',
            'provider_user_id' => 'channel-id',
            'user_access_token' => 'token',
        ]);
        $page = SocialPage::create([
            'social_account_id' => $account->id,
            'provider' => 'youtube',
            'page_id' => 'channel-id',
            'page_name' => 'Old channel name',
            'page_access_token' => 'token',
        ]);
        $page->delete();
        DB::table('social_pages')->whereKey($page->id)->update(['page_access_token' => 'invalid-old-payload']);

        $result = app(SocialAccountRepository::class)->upsertPage([
            'social_account_id' => $account->id,
            'provider' => 'youtube',
            'page_id' => 'channel-id',
        ], [
            'page_name' => 'New channel name',
            'page_access_token' => 'new-token',
            'status' => 'active',
        ]);

        $this->assertSame($page->id, $result->id);
        $this->assertNull($result->deleted_at);
        $this->assertSame('New channel name', $result->page_name);
        $this->assertSame('new-token', $result->page_access_token);
        $this->assertDatabaseCount('social_pages', 1);
    }

    public function test_an_account_can_clear_its_access_token_when_disconnected(): void
    {
        $account = SocialAccount::create([
            'user_id' => User::factory()->create()->id,
            'provider' => 'youtube',
            'provider_user_id' => 'channel-id',
            'user_access_token' => 'token',
        ]);

        $account->update(['user_access_token' => null, 'status' => 'disconnected']);

        $this->assertDatabaseHas('social_accounts', [
            'id' => $account->id,
            'user_access_token' => null,
            'status' => 'disconnected',
        ]);
    }
}
