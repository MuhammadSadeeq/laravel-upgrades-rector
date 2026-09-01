<?php

return [
    // 'default' => 'comment-queue',
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'notes' => "'default' => 'string-queue-decoy'",
    'nested' => ['default' => 'nested-queue-decoy'],
];
