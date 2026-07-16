<?php

return [
    'client_id' => env('LINKEDIN_CLIENT_ID'),
    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    'redirect_uri' => env('LINKEDIN_REDIRECT_URI'),
    'api_url' => env('LINKEDIN_API_URL', 'https://api.linkedin.com'),
    'version' => env('LINKEDIN_API_VERSION', '202507'),
    'scopes' => ['openid', 'profile', 'email', 'w_member_social', 'offline_access'],
];
