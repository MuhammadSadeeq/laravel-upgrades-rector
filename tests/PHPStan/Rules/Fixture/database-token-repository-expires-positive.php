<?php

namespace App;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function tokenRepositoryWithLegacyMinutes(): void
{
    new DatabaseTokenRepository($connection, $hasher, $tokens, 'users', 60);
}
