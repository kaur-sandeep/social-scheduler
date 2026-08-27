<?php

return [
    'client_id' => env('LINKEDIN_CLIENT_ID'),
    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    'redirect_uri' => env('LINKEDIN_REDIRECT_URI'),
    'api_url' => env('LINKEDIN_API_URL', 'https://api.linkedin.com'),
    // LinkedIn REST APIs require an active YYYYMM release header. Do not use
    // legacy values such as 20250701 (the July 2025 release was sunset).
    'version' => env('LINKEDIN_API_VERSION', '202608'),
    'scopes' => ['openid', 'profile', 'email', 'w_member_social'],
];
