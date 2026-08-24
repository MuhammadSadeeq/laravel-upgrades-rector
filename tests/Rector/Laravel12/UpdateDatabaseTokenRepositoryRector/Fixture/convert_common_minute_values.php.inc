<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

class AuthServiceProvider
{
    public function boot()
    {
        $repo1 = new DatabaseTokenRepository($connection, $hasher, 'password_resets', 'key', 60);
        $repo2 = new DatabaseTokenRepository($connection, $hasher, 'password_resets', 'key', 90);
        $repo3 = new DatabaseTokenRepository($connection, $hasher, 'password_resets', 'key', 120);

        return [$repo1, $repo2, $repo3];
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseTokenRepositoryRector;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

class AuthServiceProvider
{
    public function boot()
    {
        $repo1 = new DatabaseTokenRepository($connection, $hasher, 'password_resets', 'key', 60 * 60);
        $repo2 = new DatabaseTokenRepository($connection, $hasher, 'password_resets', 'key', 90 * 60);
        $repo3 = new DatabaseTokenRepository($connection, $hasher, 'password_resets', 'key', 120 * 60);

        return [$repo1, $repo2, $repo3];
    }
}

?>
