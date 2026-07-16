<?php

return [
    'app_id' => env('PINTEREST_APP_ID'),
    'app_secret' => env('PINTEREST_APP_SECRET'),
    'redirect_uri' => env('PINTEREST_REDIRECT_URI'),
    'api_url' => env('PINTEREST_API_URL', 'https://api.pinterest.com/v5'),
    'scopes' => ['boards:read', 'boards:write', 'pins:read', 'pins:write'],
];
