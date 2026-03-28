<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testAlreadyConverted()
{
    $repo = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 60 * 60);
}

?>
