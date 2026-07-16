<?php

return [
    'app_id' => env('FACEBOOK_APP_ID'),
    'app_secret' => env('FACEBOOK_APP_SECRET'),
    'redirect_uri' => env('FACEBOOK_REDIRECT_URI'),
    'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v23.0'),
   'scopes' => [
        'pages_show_list',
        'pages_manage_posts',
        'pages_read_engagement',
        'pages_read_user_content',
        'business_management',
        'instagram_basic',
        'instagram_content_publish',
        'public_profile',
        'email',
    ],
];
