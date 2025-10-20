<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testAllSupportedValues()
{
    // Test all supported minute values
    return [
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 15),    // 15 minutes
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 30),    // 30 minutes
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 180),   // 3 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 240),   // 4 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 360),   // 6 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 720),   // 12 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 1440),  // 24 hours
    ];
}

?>
-----
<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testAllSupportedValues()
{
    // Test all supported minute values
    return [
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 15 * 60),    // 15 minutes
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 30 * 60),    // 30 minutes
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 180 * 60),   // 3 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 240 * 60),   // 4 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 360 * 60),   // 6 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 720 * 60),   // 12 hours
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 1440 * 60),  // 24 hours
    ];
}

?>