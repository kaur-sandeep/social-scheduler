<?php

return [
    'client_key' => env('TIKTOK_CLIENT_KEY'),
    'client_secret' => env('TIKTOK_CLIENT_SECRET'),
    'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
    'api_url' => env('TIKTOK_API_URL', 'https://open.tiktokapis.com'),
    'default_privacy' => env('TIKTOK_DEFAULT_PRIVACY', 'SELF_ONLY'),
    'scopes' => ['user.info.basic', 'video.publish'],
];
