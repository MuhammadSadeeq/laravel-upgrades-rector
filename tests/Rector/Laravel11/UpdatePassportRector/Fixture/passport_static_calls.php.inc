<?php

use Laravel\Passport\Passport;

class PassportConfiguration
{
    public function configure()
    {
        Passport::routes();
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}

?>
-----
<?php

use Laravel\Passport\Passport;

class PassportConfiguration
{
    public function configure()
    {
        Passport::routes();
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}

?>