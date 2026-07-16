# Provider setup

This application uses official APIs only. Create an app with each provider, complete its review requirements, and connect an account before scheduling. Tokens are encrypted at rest; never put them in source control.

## Runtime requirements

Run `php artisan storage:link`, a queue worker (`php artisan queue:work`), and the scheduler (`php artisan schedule:work`, or cron calling `php artisan schedule:run` every minute). `APP_URL` and uploaded media must be reachable over public HTTPS: Meta, TikTok, Pinterest, Threads, and Instagram fetch media from that URL.

## Provider capabilities

| Provider | Official API | Required destination identifier |
| --- | --- | --- |
| Facebook | Graph API | Facebook Page ID |
| Instagram | Instagram Graph API | Facebook Page with an Instagram professional account |
| LinkedIn | Share on LinkedIn API | Connected member profile |
| TikTok | Content Posting API | TikTok creator account |
| X | X API v2 | X account with posting entitlement |
| Pinterest | Pinterest API v5 | Board ID |
| YouTube | YouTube Data API v3 | Channel OAuth token |
| Threads | Threads API | Threads user ID |

Provider app approval determines which features are available. TikTok `PULL_FROM_URL` must be enabled for the developer app. Do not mark a post as published based on an upload acknowledgement alone; this project only does so after the provider's publish endpoint returns success.

## Environment

Copy `.env.example`, set the listed provider values, and set `FILESYSTEM_DISK=public`. Facebook, YouTube, LinkedIn, X, Pinterest, and TikTok OAuth are wired into the Accounts page.

### TikTok

Create a TikTok for Developers app, add **Login Kit** and the **Content Posting API**, enable **Direct Post**, and register `https://your-domain/tiktok/callback` as the redirect URI. Request approval for `user.info.basic` and `video.publish`, then set `TIKTOK_CLIENT_KEY`, `TIKTOK_CLIENT_SECRET`, and `TIKTOK_REDIRECT_URI`. Add the public HTTPS media domain or URL prefix to the app's URL properties before using `PULL_FROM_URL`. TikTok checks each creator's allowed privacy options at publish time; set `TIKTOK_DEFAULT_PRIVACY` to an allowed value (normally `SELF_ONLY` while the app is unaudited). TikTok accepts one video per scheduled post and the app waits for TikTok's status API to report completion before marking it published.

### LinkedIn

Create an app in the LinkedIn Developer Portal, add the **Sign In with LinkedIn using OpenID Connect** and **Share on LinkedIn** products, and register `https://your-domain/linkedin/callback`. Add its client ID and secret as `LINKEDIN_CLIENT_ID` and `LINKEDIN_CLIENT_SECRET`; set `LINKEDIN_REDIRECT_URI` to that exact callback. The implementation publishes text posts to the connected member profile. Organization posting and LinkedIn media uploads require additional LinkedIn product access and are intentionally not enabled.

### X

Create an X developer app with OAuth 2.0 enabled, add `https://your-domain/twitter/callback` as a callback URL, and enable the `tweet.write`, `tweet.read`, `users.read`, `media.write`, and `offline.access` scopes. Set `TWITTER_CLIENT_ID`, optional `TWITTER_CLIENT_SECRET` (for a confidential client), and `TWITTER_REDIRECT_URI`. Your X API plan must include write access. This implementation publishes text posts; attachment upload is not enabled yet.

### Pinterest

Create and obtain approval for a Pinterest developer app, register `https://your-domain/pinterest/callback`, and request `boards:read`, `boards:write`, `pins:read`, and `pins:write`. Set `PINTEREST_APP_ID`, `PINTEREST_APP_SECRET`, and `PINTEREST_REDIRECT_URI`. Connecting the account automatically imports its boards. Pinterest posts currently require one image and a publicly reachable HTTPS `APP_URL` so Pinterest can fetch the image.
