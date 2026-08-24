<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testInsufficientArgs()
{
    $repo1 = new DatabaseTokenRepository($connection);
    $repo2 = new DatabaseTokenRepository($connection, $hasher);
    $repo3 = new DatabaseTokenRepository($connection, $hasher, 'table');
    $repo4 = new DatabaseTokenRepository($connection, $hasher, 'table', 'key');

    return [$repo1, $repo2, $repo3, $repo4];
}

?>
