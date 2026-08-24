<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function createTokenRepository()
{
    $repo = new DatabaseTokenRepository(
        $connection,
        $hasher,
        'password_reset_tokens',
        'email',
        60
    );
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function createTokenRepository()
{
    $repo = new DatabaseTokenRepository(
        $connection,
        $hasher,
        'password_reset_tokens',
        'email',
        60 * 60
    );
}

?>
