<?php

return [
    'max_tables' => (int) env('VTT_MAX_TABLES', 3),

    'max_table_upload_mb' => (int) env('VTT_TABLE_UPLOAD_QUOTA_MB', 50),

    'max_user_upload_mb' => env('VTT_USER_UPLOAD_QUOTA_MB'),

    'source_path' => env('VTT_SOURCE_PATH', base_path('current-source')),

    'tables_path' => env('VTT_TABLES_PATH', public_path('vtt/user')),

    'slug_length' => 10,

    'allowed_origins' => env('VTT_ALLOWED_ORIGINS', '*'),
];
