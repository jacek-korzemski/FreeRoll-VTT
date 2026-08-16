<?php

return [
    'max_tables' => 3,

    'source_path' => env('VTT_SOURCE_PATH', base_path('current-source')),

    'tables_path' => env('VTT_TABLES_PATH', public_path('vtt/user')),

    'slug_length' => 10,

    'allowed_origins' => env('VTT_ALLOWED_ORIGINS', '*'),
];
