<?php

namespace App;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function tokenRepositoryBoundaryValues(int $expires): void
{
    new DatabaseTokenRepository($connection, $hasher, $tokens, 'users', 600);
    new DatabaseTokenRepository($connection, $hasher, $tokens, 'users', 599);
    new DatabaseTokenRepository($connection, $hasher, $tokens, 'users', $expires);
}
