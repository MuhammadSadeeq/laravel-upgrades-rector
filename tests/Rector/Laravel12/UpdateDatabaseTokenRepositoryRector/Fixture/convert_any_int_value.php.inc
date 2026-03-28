<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testUncommonMinuteValues()
{
    $repo1 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 45);
    $repo2 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 90);
    $repo3 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 10);

    return [$repo1, $repo2, $repo3];
}

?>
-----
<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testUncommonMinuteValues()
{
    $repo1 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 45 * 60);
    $repo2 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 90 * 60);
    $repo3 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 10 * 60);

    return [$repo1, $repo2, $repo3];
}

?>
