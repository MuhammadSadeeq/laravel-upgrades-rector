<?php

function createTokenRepository()
{
    // Should work with short class name
    return new DatabaseTokenRepository(
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
    // Should work with short class name
    return new DatabaseTokenRepository(
        $connection,
        $hasher,
        'password_reset_tokens',
        'email',
        60 * 60
    );
}

?>