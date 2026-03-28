<?php

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
