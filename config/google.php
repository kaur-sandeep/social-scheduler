<?php

return [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    'youtube_upload_url' => 'https://www.googleapis.com/upload/youtube/v3/videos',
    'youtube_api_url' => 'https://www.googleapis.com/youtube/v3',
    'youtube_privacy' => env('YOUTUBE_DEFAULT_PRIVACY', 'private'),
    'scopes' => [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
    ],
];
