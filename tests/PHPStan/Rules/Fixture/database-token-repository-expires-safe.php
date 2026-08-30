<?php

namespace App;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function tokenRepositoryWithSeconds(): void
{
    new DatabaseTokenRepository($connection, $hasher, $tokens, 'users', 3600);
}
