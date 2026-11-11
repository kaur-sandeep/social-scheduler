<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Repositories\SocialAccountRepository;
use App\Services\ProjectCredentialService;
use App\Services\Social\Clients\TikTokClient;
use App\Services\Social\Concerns\InteractsWithPosts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Models\SocialPage;
use Carbon\Carbon;
use RuntimeException;

class TikTokService
{
    use InteractsWithPosts;
    public function __construct(
        private readonly TikTokClient $client,
        private readonly SocialAccountRepository $accounts,
        private readonly ProjectCredentialService $credentials,
    ) {}

    public function authorizationUrl(): string
    {
        $credential = $this->credentials->fromSession('tiktok');
        $state = Str::random(40);
        session(['tiktok_oauth_state' => $state]);

        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => $credential->client_id, 'response_type' => 'code',
            'scope' => implode(',', config('tiktok.scopes')), 'redirect_uri' => $this->redirectUri($credential->redirect_uri), 'state' => $state,
        ]);
    }

    public function connect(int $userId, string $code): SocialAccount
    {
       
        $credential = $this->credentials->fromSession('tiktok');
        $token = $this->token($credential->client_id, $credential->client_secret, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri($credential->redirect_uri)]);
        // dd($token);
        $profile = $this->profile($token['access_token']);
        $identity = (string) ($token['open_id'] ?? data_get($profile, 'data.open_id') ?? '');
        if ($identity === '') throw new RuntimeException('TikTok did not return an account identity.');

        $account = $this->accounts->upsertAccount(['user_id' => $userId, 'provider' => 'tiktok', 'provider_user_id' => $identity], [
            'provider_username' => data_get($profile, 'data.display_name'), 'name' => data_get($profile, 'data.display_name') ?: $identity,
            'user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'status' => 'active', 'connected_at' => now(), 'disconnected_at' => null,
        ]);
        $this->accounts->upsertPage(['social_account_id' => $account->id, 'provider' => 'tiktok', 'page_id' => $identity], [
            'page_name' => data_get($profile, 'data.display_name') ?: 'TikTok account', 'category' => 'Creator account',
            'profile_image' => data_get($profile, 'data.avatar_url'), 'page_access_token' => $token['access_token'],
            'permissions' => explode(',', (string) ($token['scope'] ?? implode(',', config('tiktok.scopes')))), 'status' => 'active',
        ]);

        return $account->refresh();
    }

    public function publish(Post $post): array
    {
        $page = $this->page($post, 'tiktok');
        $video = $post->media->firstWhere('media_type', 'video');
        if (! $video) throw new RuntimeException('TikTok publishing requires one video.');
        $token = $this->accessToken($page->account);
        $creator = $this->client->creatorInfo($post, $token)['data'] ?? [];
        $privacy = config('tiktok.default_privacy');
        if (! in_array($privacy, $creator['privacy_level_options'] ?? [], true)) throw new RuntimeException('TikTok does not permit the configured privacy level for this creator. Set TIKTOK_DEFAULT_PRIVACY to one of the creator\'s available options.');

        return $this->client->publish($post, $token, ['post_info' => ['title' => $post->message, 'privacy_level' => $privacy, 'disable_duet' => (bool) ($creator['duet_disabled'] ?? false), 'disable_comment' => (bool) ($creator['comment_disabled'] ?? false), 'disable_stitch' => (bool) ($creator['stitch_disabled'] ?? false)], 'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $this->mediaUrl($video->path)]]);
    }

    public function status(Post $post): array { return $this->client->status($post, $this->accessToken($this->page($post, 'tiktok')->account), $post->provider_post_id); }

    private function accessToken(SocialAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isFuture()) return $account->user_access_token;
        if (! $account->refresh_token) throw new RuntimeException('Reconnect TikTok: the access token has expired.');
        $credential = $this->credentials->forProject($account->project, 'tiktok');
        $token = $this->token($credential->client_id, $credential->client_secret, ['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        $account->update(['user_access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'] ?? $account->refresh_token, 'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 0))]);
        $account->pages()->update(['page_access_token' => $token['access_token']]);

        return $token['access_token'];
    }

    private function token(string $clientKey, string $clientSecret, array $payload): array
    {
        $response = Http::asForm()->timeout(30)->post(config('tiktok.api_url').'/v2/oauth/token/', array_merge($payload, ['client_key' => $clientKey, 'client_secret' => $clientSecret]));
        if ($response->failed()) throw new RuntimeException(data_get($response->json(), 'error_description', 'TikTok authorization failed.'));

        return $response->json() ?? [];
    }

    private function profile(string $token): array
    {
        $response = Http::acceptJson()->withToken($token)->timeout(30)->get(config('tiktok.api_url').'/v2/user/info/', ['fields' => 'open_id,display_name,avatar_url']);
        // dd($response);
        if ($response->failed()) throw new RuntimeException('Could not read TikTok account information.');

        return $response->json() ?? [];
    }


    
// code commentd on 10/11/2026 
// public function dashboardForDailyReport(
//     SocialPage $page,
//     string $since,
//     string $until,
//     bool $includeMedia = true
// ): array {

//     $account = $page->account;

// if (
//     ! $account ||
//     $page->provider !== 'tiktok' ||
//     ! in_array($account->status, ['active', 'reauthorization_required'], true) ||
//     $page->status !== 'active'
// ) {
//     throw new RuntimeException('This TikTok account is disconnected.');
// }

// // Get TikTok access token from SocialPage
// $token = $this->getValidAccessToken($account);

// if (! $token) {
//     throw new RuntimeException('TikTok access token is missing.');
// }

//     $baseUrl = 'https://open.tiktokapis.com/v2';

//     /*
//     |--------------------------------------------------------------------------
//     | 1. Fetch TikTok Account Information
//     |--------------------------------------------------------------------------
//     */

//     $profileResponse = Http::withToken($token)
//         ->get($baseUrl . '/user/info/', [
//             'fields' => implode(',', [
//                 // 'open_id',
//                 // 'username',
//                 // 'display_name',
//                 // 'avatar_url',
//                 // 'profile_deep_link',
//                 // 'follower_count',
//                 // 'following_count',
//                 // 'likes_count',
//                 // 'video_count',
//                  'open_id',
//                     'avatar_url',
//                     'display_name',
//             ]),
//         ]);

//     if (! $profileResponse->successful()) {
//         throw new RuntimeException(
//             'TikTok profile request failed: ' .
//             $profileResponse->body()
//         );
//     }

//     $profileResult = $profileResponse->json();

//     if (($profileResult['error']['code'] ?? 'ok') !== 'ok') {
//         throw new RuntimeException(
//             $profileResult['error']['message'] ?? 'TikTok profile API error.'
//         );
//     }

//     $profile = $profileResult['data']['user'] ?? [];

//     if (empty($profile)) {
//         throw new RuntimeException('TikTok profile data not found.');
//     }

//     if ($account->status === 'reauthorization_required') {
//         $account->update(['status' => 'active']);
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | 2. Normalize Account Insights
//     |--------------------------------------------------------------------------
//     */

//     $insights = [];
//     $unavailable = [];

//     $accountMetrics = [
//         'follower_count',
//         'following_count',
//         'likes_count',
//         'video_count',
//     ];

//     foreach ($accountMetrics as $metric) {

//         $value = $profile[$metric] ?? null;

//         if ($value === null) {
//             $unavailable[] = $metric;
//         }

//         $insights[] = [
//             'name' => $metric,
//             'total_value' => [
//                 'value' => $value,
//             ],
//             'values' => [
//                 [
//                     'value' => $value,
//                     'end_time' => $until,
//                 ],
//             ],
//         ];
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | 3. Fetch TikTok Videos
//     |--------------------------------------------------------------------------
//     */

//     $media = [];
//     $mediaUnavailable = false;
//     $mediaInsightsUnavailable = [];

//     if ($includeMedia) {

//         try {

//             $videos = [];
//             $cursor = 0;
//             $hasMore = true;

//             while ($hasMore) {

//                 $videoResponse = Http::withToken($token)
//                     ->post($baseUrl . '/video/list/?fields=' . implode(',', [
//                         'id',
//                         'title',
//                         'video_description',
//                         'create_time',
//                         'cover_image_url',
//                         'share_url',
//                         'duration',
//                         'view_count',
//                         'like_count',
//                         'comment_count',
//                         'share_count',
//                     ]), [
//                         'max_count' => 20,
//                         'cursor' => $cursor,
//                     ]);

//                 if (! $videoResponse->successful()) {
//                     throw new RuntimeException(
//                         'TikTok video request failed: ' .
//                         $videoResponse->body()
//                     );
//                 }

//                 $videoResult = $videoResponse->json();

//                 $errorCode = $videoResult['error']['code'] ?? 'ok';

//                 if ($errorCode !== 'ok') {
//                     throw new RuntimeException(
//                         $videoResult['error']['message'] ??
//                         'TikTok video API error.'
//                     );
//                 }

//                 $videoData = $videoResult['data'] ?? [];

//                 $videos = array_merge(
//                     $videos,
//                     $videoData['videos'] ?? []
//                 );

//                 $hasMore = $videoData['has_more'] ?? false;
//                 $cursor = $videoData['cursor'] ?? 0;

//                 // Stop once we have reached videos older than the range.
//                 if (! empty($videos)) {
//                     $oldest = min(array_column($videos, 'create_time'));

//                     if ($oldest < Carbon::parse($since)->startOfDay()->timestamp) {
//                         break;
//                     }
//                 }
//             }

//             /*
//             |--------------------------------------------------------------------------
//             | 4. Normalize Video Data
//             |--------------------------------------------------------------------------
//             */

//             foreach ($videos as $item) {

//                 if (empty($item['create_time'])) {
//                     continue;
//                 }

//                 $publishedAt = Carbon::createFromTimestampUTC(
//                     $item['create_time']
//                 );

//                 $date = $publishedAt->copy()
//                     ->setTimezone(config('app.timezone', 'UTC'))
//                     ->toDateString();

//                 if ($date < $since || $date > $until) {
//                     continue;
//                 }

//                 $metrics = [
//                     'views' => $item['view_count'] ?? null,
//                     'likes' => $item['like_count'] ?? null,
//                     'comments' => $item['comment_count'] ?? null,
//                     'shares' => $item['share_count'] ?? null,
//                 ];

//                 foreach ($metrics as $name => $value) {

//                     if ($value === null) {
//                         $mediaInsightsUnavailable[] = [
//                             'media_id' => $item['id'],
//                             'metric' => $name,
//                             'reason' => 'not_returned',
//                         ];
//                     }
//                 }

//                 $media[] = [
//                     'id' => $item['id'],
//                     'text' => $item['video_description'] ?? $item['title'] ?? '',
//                     'type' => 'VIDEO',
//                     'product_type' => 'VIDEO',
//                     'url' => $item['share_url'] ?? null,
//                     'thumbnail' => $item['cover_image_url'] ?? null,
//                     'published_at' => $publishedAt->toIso8601String(),
//                     'duration' => $item['duration'] ?? null,
//                     'metrics' => $metrics,
//                 ];
//             }

//         } catch (RuntimeException $exception) {

//             $mediaUnavailable = true;

//             // Log::notice(
//             //     'TikTok video details are unavailable.',
//             //     [
//             //         'social_page_id' => $page->id,
//             //         'message' => $exception->getMessage(),
//             //     ]
//             // );
//         }
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | 5. Update Social Page Information
//     |--------------------------------------------------------------------------
//     */

//     $page->update([
//         'last_synced_at' => now(),
//         'page_name' => $profile['username'] ?? $profile['display_name'] ?? $page->page_name,
//         'profile_image' => $profile['avatar_url'] ?? $page->profile_image,
//     ]);

//     /*
//     |--------------------------------------------------------------------------
//     | 6. Return Dashboard Data
//     |--------------------------------------------------------------------------
//     */

//     return [
//         'account' => [
//             'username' => $profile['username'] ?? null,
//             'display_name' => $profile['display_name'] ?? null,
//             'profile_image' => $profile['avatar_url'] ?? null,
//             'profile_url' => $profile['profile_deep_link'] ?? null,
//             'followers_count' => $profile['follower_count'] ?? null,
//             'following_count' => $profile['following_count'] ?? null,
//             'likes_count' => $profile['likes_count'] ?? null,
//             'media_count' => $profile['video_count'] ?? null,
//         ],

//         'insights' => $insights,

//         'media' => $media,

//         'media_unavailable' => $mediaUnavailable,

//         'media_insights_unavailable' => $mediaInsightsUnavailable,

//         'insights_unavailable' => $unavailable,
//     ];
// }



public function dashboardForDailyReport(SocialPage $page, string $since, string $until, bool $includeMedia = true, ?string $timezone = null): array
{
    $account = $page->account;

    if (
        ! $account ||
        $page->provider !== 'tiktok' ||
        ! in_array($account->status, ['active', 'reauthorization_required'], true) ||
        $page->status !== 'active'
    ) {
        throw new RuntimeException('This TikTok account is disconnected.');
    }

    $token = $this->accessToken($account);
    $baseUrl = 'https://open.tiktokapis.com/v2';

    // 1. Profile + stats
    $profileResponse = Http::withToken($token)->get($baseUrl.'/user/info/', [
        'fields' => implode(',', [
            'open_id', 'avatar_url', 'display_name',                                   // user.info.basic
            'username', 'profile_deep_link', 'bio_description', 'is_verified',         // user.info.profile
            'follower_count', 'following_count', 'likes_count', 'video_count',         // user.info.stats
        ]),
    ]);

    if (! $profileResponse->successful()) {
        throw new RuntimeException('TikTok profile request failed: '.$profileResponse->body());
    }

    $profileResult = $profileResponse->json();

    if (($profileResult['error']['code'] ?? 'ok') !== 'ok') {
        throw new RuntimeException($profileResult['error']['message'] ?? 'TikTok profile API error.');
    }

    $profile = $profileResult['data']['user'] ?? [];

    if (empty($profile)) {
        throw new RuntimeException('TikTok profile data not found.');
    }

    if ($account->status === 'reauthorization_required') {
        $account->update(['status' => 'active']);
    }

    // 2. Account-level insights
    $insights = [];
    $unavailable = [];

    foreach (['follower_count', 'following_count', 'likes_count', 'video_count'] as $metric) {
        $value = $profile[$metric] ?? null;

        if ($value === null) {
            $unavailable[] = $metric;
        }

        $insights[] = [
            'name'        => $metric,
            'total_value' => ['value' => $value],
            'values'      => [['value' => $value, 'end_time' => $until]],
        ];
    }

    // TikTok ye deta hi nahi, view mein inhe "unavailable" dikhana
    array_push($unavailable, 'unique_viewers', 'saves', 'net_follower_change');

    // 3. Videos
    $media = [];
    $mediaUnavailable = false;
    $mediaInsightsUnavailable = [];

    if ($includeMedia) {
        try {
            $videos = [];
            $cursor = 0;
            $hasMore = true;
            $sinceTs = Carbon::parse($since)->startOfDay()->timestamp;
            $guard = 0;

            while ($hasMore && $guard++ < 25) {
                $videoResponse = Http::withToken($token)->post(
                    $baseUrl.'/video/list/?fields='.implode(',', [
                        'id', 'title', 'video_description', 'create_time', 'cover_image_url',
                        'share_url', 'embed_link', 'duration', 'height', 'width',
                        'view_count', 'like_count', 'comment_count', 'share_count',
                    ]),
                    ['max_count' => 20, 'cursor' => $cursor]
                );

                if (! $videoResponse->successful()) {
                    throw new RuntimeException('TikTok video request failed: '.$videoResponse->body());
                }

                $videoResult = $videoResponse->json();

                if (($videoResult['error']['code'] ?? 'ok') !== 'ok') {
                    throw new RuntimeException($videoResult['error']['message'] ?? 'TikTok video API error.');
                }

                $videoData = $videoResult['data'] ?? [];
                $batch = $videoData['videos'] ?? [];
                $videos = array_merge($videos, $batch);

                $hasMore = (bool) ($videoData['has_more'] ?? false);
                $cursor = $videoData['cursor'] ?? 0;

                // Videos newest-first aate hain; is batch ka sabse purana video range se bahar ho to ruk jao
                if (! empty($batch) && min(array_column($batch, 'create_time')) < $sinceTs) {
                    break;
                }
            }

            $tz = $timezone ?: $this->resolveTimezone();

            foreach ($videos as $item) {
                if (empty($item['create_time'])) {
                    continue;
                }

                $publishedAt = Carbon::createFromTimestampUTC($item['create_time']);
                $date = $publishedAt->copy()->setTimezone($tz)->toDateString();

                if ($date < $since || $date > $until) {
                    continue;
                }

                $metrics = [
                    'views'    => $item['view_count'] ?? null,
                    'likes'    => $item['like_count'] ?? null,
                    'comments' => $item['comment_count'] ?? null,
                    'shares'   => $item['share_count'] ?? null,
                ];

                foreach ($metrics as $name => $value) {
                    if ($value === null) {
                        $mediaInsightsUnavailable[] = ['media_id' => $item['id'], 'metric' => $name, 'reason' => 'not_returned'];
                    }
                }

                $media[] = [
                    'id'           => $item['id'],
                    'text'         => $item['video_description'] ?? $item['title'] ?? '',
                    'type'         => 'VIDEO',
                    'product_type' => 'VIDEO',
                    'url'          => $item['share_url'] ?? null,
                    'thumbnail'    => $item['cover_image_url'] ?? null,
                    'published_at' => $publishedAt->toIso8601String(),
                    'duration'     => $item['duration'] ?? null,
                    'metrics'      => $metrics,
                ];
            }
        } catch (RuntimeException $exception) {
            $mediaUnavailable = true;
            Log::notice('TikTok video details are unavailable.', [
                'social_page_id' => $page->id,
                'message'        => $exception->getMessage(),
            ]);
        }

        // 4. Period totals (videos ke metrics ka jod) -> views, likes, comments, shares cards ke liye
        if (! $mediaUnavailable) {
            foreach (['views', 'likes', 'comments', 'shares'] as $name) {
                $total = 0;
                foreach ($media as $m) {
                    $total += (int) ($m['metrics'][$name] ?? 0);
                }

                $insights[] = [
                    'name'        => $name,
                    'total_value' => ['value' => $total],
                    'values'      => [['value' => $total, 'end_time' => $until]],
                ];
            }
        }
    }

    // 5. Page update
    $page->update([
        'last_synced_at' => now(),
        'page_name'      => $profile['display_name'] ?? $page->page_name,
        'profile_image'  => $profile['avatar_url'] ?? $page->profile_image,
    ]);

    // 6. Result
    return [
        'account' => [
            'username'        => $profile['username'] ?? null,
            'display_name'    => $profile['display_name'] ?? null,
            'bio'             => $profile['bio_description'] ?? null,
            'is_verified'     => $profile['is_verified'] ?? null,
            'profile_image'   => $profile['avatar_url'] ?? null,
            'profile_url'     => $profile['profile_deep_link'] ?? null,
            'followers_count' => $profile['follower_count'] ?? null,
            'following_count' => $profile['following_count'] ?? null,
            'likes_count'     => $profile['likes_count'] ?? null,
            'media_count'     => $profile['video_count'] ?? null,
        ],
        'insights'                   => $insights,
        'media'                      => $media,
        'media_unavailable'          => $mediaUnavailable,
        'media_insights_unavailable' => $mediaInsightsUnavailable,
        'insights_unavailable'       => $unavailable,
    ];
}

private function resolveTimezone(): string
{
    return auth()->user()?->timezone ?: config('app.timezone', 'UTC');
}
    public function dashboard(SocialPage $page, string $since, string $until): array
    {
        return $this->dashboardForDailyReport($page, $since, $until);
    }

    
private function getValidAccessToken($account): string
{
    // Return current token if it is still valid.
    if (
        $account->user_access_token &&
        $account->token_expires_at &&
        now()->lt($account->token_expires_at)
    ) {
        return $account->user_access_token;
    }

    // Refresh token is required when access token expires.
    if (! $account->refresh_token) {
        throw new RuntimeException(
            'TikTok refresh token is missing. Please reconnect TikTok.'
        );
    }

    $response = Http::asForm()->post(
        'https://open.tiktokapis.com/v2/oauth/token/',
        [
            'client_key' => config('services.tiktok.client_key'),
            'client_secret' => config('services.tiktok.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $account->refresh_token,
        ]
    );

    $result = $response->json();

    if (
        ! $response->successful() ||
        empty($result['access_token'])
    ) {
        // Log::error('TikTok token refresh failed.', [
        //     'account_id' => $account->id,
        //     'response' => $result,
        // ]);

        throw new RuntimeException(
            'TikTok token refresh failed. Please reconnect TikTok.'
        );
    }

    // Save NEW access token and NEW rotated refresh token.
    $account->update([
        'user_access_token' => $result['access_token'],
        'refresh_token' => $result['refresh_token'],
        'token_expires_at' => now()->addSeconds($result['expires_in']),
    ]);

    return $result['access_token'];
}

    private function redirectUri(?string $configuredUri = null): string { return $configuredUri ?: URL::route('tiktok.callback'); }
}
