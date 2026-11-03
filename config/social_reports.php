<?php

return [
    'enabled' => (bool) env('MANAGER_REPORT_ENABLED', false),
    'recipients' => array_values(array_filter(array_map(
        'trim',
        preg_split('/[,;]+/', (string) env('MANAGER_REPORT_EMAIL', '')) ?: []
    ))),
    'timezone' => env('MANAGER_REPORT_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kolkata')),
];
