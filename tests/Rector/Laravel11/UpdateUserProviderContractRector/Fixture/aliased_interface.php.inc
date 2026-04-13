<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;

class LegacyUserProvider implements UserProviderContract
{
}

?>
-----
<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;

class LegacyUserProvider implements UserProviderContract
{
    public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false): void
    {
        // TODO: Implement rehashPasswordIfRequired() method.
    }
}

?>
