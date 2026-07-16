<?php

return [
    'client_id' => env('TWITTER_CLIENT_ID'),
    'client_secret' => env('TWITTER_CLIENT_SECRET'),
    'redirect_uri' => env('TWITTER_REDIRECT_URI'),
    'api_url' => env('TWITTER_API_URL', 'https://api.x.com'),
    'scopes' => ['tweet.read', 'tweet.write', 'users.read', 'media.write', 'offline.access'],
];
