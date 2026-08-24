<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testAllSupportedValues()
{
    return [
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 15),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 30),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 180),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 240),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 360),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 720),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 1440),
    ];
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testAllSupportedValues()
{
    return [
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 15 * 60),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 30 * 60),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 180 * 60),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 240 * 60),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 360 * 60),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 720 * 60),
        new DatabaseTokenRepository($conn, $hash, 'table', 'key', 1440 * 60),
    ];
}

?>
