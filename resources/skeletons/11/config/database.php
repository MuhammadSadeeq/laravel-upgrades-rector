<?php

return [
    'connections' => [
        'sqlite' => [
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => env('DB_JOURNAL', 'wal'),
            'synchronous' => env('DB_SYNCHRONOUS', 'normal'),
        ],
    ],
];
