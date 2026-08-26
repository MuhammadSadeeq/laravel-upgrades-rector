<?php

return [
    'connections' => [
        'sqlite' => [
            'busy_timeout' => null,
            'journal_mode' => env('DB_JOURNAL', 'wal'),
        ],
    ],
];
