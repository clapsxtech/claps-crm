<?php
return [
    'sheets' => [
        'enabled' => (bool) env('SHEETS_SYNC_ENABLED', false),
        'spreadsheet_id' => env('GOOGLE_SPREADSHEET_ID', ''),
        'credentials_path' => env('GOOGLE_CREDENTIALS_PATH', storage_path('credentials.json')),
        'claim_timeout_seconds' => 300,
        'retry_delay_seconds' => 60,
    ],
];
