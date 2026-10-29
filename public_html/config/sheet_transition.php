<?php

$bandUnits = json_decode(env('ERP_SHEETS_BAND_UNITS', '{}'), true);

return [
    // Independent of the legacy bidirectional integration. Disabled until an operator enables it.
    'enabled' => (bool) env('ERP_SHEETS_TRANSITION_ENABLED', false),
    'authority' => env('ERP_DATA_AUTHORITY', 'application'),
    'credentials_path' => env('GOOGLE_CREDENTIALS_PATH', storage_path('credentials.json')),
    'band_units' => is_array($bandUnits) ? $bandUnits : [],
    'stale_minutes' => 60,
    'lease_seconds' => 300,
    'max_grid_rows' => 100000,
    'max_populated_rows' => 25000,
    'max_payload_bytes' => 8 * 1024 * 1024,
    // Reviewed source contracts. Changes to these mappings require another preview.
    'sources' => [
        'students' => [
            'label'=>'Student registration', 'page'=>'people',
            'spreadsheet_id'=>'1BlPibi73uveaxU7f03u-9gq0Xlfjd3lAce4ZFQyZj-c', 'gid'=>270416360,
            'first_row'=>2, 'last_column'=>'AC',
            'headers'=>['C1'=>'STATUS', 'E1'=>'Hour Rate', 'AB1'=>'🎓Class', 'AC1'=>'📚Syllabus'],
            'fields'=>['band'=>0,'name'=>1,'status'=>2,'code'=>3,'grade'=>27,'syllabus'=>28],
        ],
        'teachers' => [
            'label'=>'Teacher registration', 'page'=>'people',
            'spreadsheet_id'=>'15Ieymxt3RVaU3jIwuzS_WDuJZRv6Ghp5oyYBiGAnZWE', 'gid'=>1217373019,
            'first_row'=>3, 'last_column'=>'G',
            'headers'=>['C1'=>'Full Name','D1'=>'Teacher Code','E1'=>'Status','G1'=>'Subject'],
            'fields'=>['band'=>0,'category'=>1,'name'=>2,'code'=>3,'status'=>4,'type'=>5,'subject'=>6],
        ],
        'attendance' => [
            'label'=>'Reported class hours', 'page'=>'attendance',
            'spreadsheet_id'=>'1WOf_M31Vf2cGdIqzFxD4fX3X6hIVWj4acawcIA4qNu0', 'gid'=>0,
            'first_row'=>2, 'last_column'=>'H',
            'headers'=>['A1'=>'ID','C1'=>'Teacher Code','E1'=>'Student Code','F1'=>'Date','G1'=>'Work Hour'],
            'fields'=>['id'=>0,'teacher_name'=>1,'teacher_code'=>2,'student_name'=>3,'student_code'=>4,'date'=>5,'hours'=>6],
        ],
        'balances' => [
            'label'=>'Reported student balances', 'page'=>'money',
            'spreadsheet_id'=>'1F46Wno9r2iUvqVk6pGfAdtCVZua3X7oKn-nZF93t6WU', 'gid'=>611361880,
            'first_row'=>3, 'last_column'=>'S',
            'headers'=>['A2'=>'STUDENT NAME','B2'=>'CODE','K2'=>'SECTION','S2'=>'Current Balance'],
            'fields'=>['name'=>0,'code'=>1,'status'=>3,'band'=>10,'balance'=>18],
        ],
        'timetable' => [
            'label'=>'Weekly timetable', 'page'=>'timetable',
            'spreadsheet_id'=>'1UMCwAlzpHu1hPChCK5dsoYrgjEoTkmHPt9UAmeM91AQ', 'gid'=>0,
            'first_row'=>4, 'last_column'=>'AE',
            'headers'=>['C1'=>'CLASS & Syllabus','F1'=>'MONDAY','I1'=>'TUESDAY','Q1'=>'THURSDAY','AC1'=>'SUNDAY'],
            'fields'=>['student'=>0,'class'=>2],
        ],
    ],
];
