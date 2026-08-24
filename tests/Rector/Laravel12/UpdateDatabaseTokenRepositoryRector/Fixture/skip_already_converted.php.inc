<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testAlreadyConverted()
{
    $repo = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 60 * 60);
}

?>
