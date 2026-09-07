<?php

return [
    'max_media_files' => env('SOCIAL_MAX_MEDIA_FILES', 10),
    // Keep this in sync with public/.user.ini. Laravel's `max` file rule uses KiB.
    'max_upload_kilobytes' => env('SOCIAL_MAX_UPLOAD_KILOBYTES', 102400),
    'public_media_url_required' => true,
];
