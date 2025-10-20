<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testNonCommonValues()
{
    // Should not change - non-standard minute values
    $repo1 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 3600); // Already in seconds
    $repo2 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 45);   // Uncommon minute value
    $repo3 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 600);  // 10 minutes, uncommon

    return [$repo1, $repo2, $repo3];
}

?>
-----
<?php

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

function testNonCommonValues()
{
    // Should not change - non-standard minute values
    $repo1 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 3600); // Already in seconds
    $repo2 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 45);   // Uncommon minute value
    $repo3 = new DatabaseTokenRepository($conn, $hash, 'table', 'key', 600);  // 10 minutes, uncommon

    return [$repo1, $repo2, $repo3];
}

?>