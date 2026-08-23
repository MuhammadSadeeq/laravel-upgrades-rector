<?php

namespace App\Time;

use Carbon\CarbonTimeZone;

final class Factory
{
    public function make(): array
    {
        $defaultUtc = new CarbonTimeZone();
        $explicit = new CarbonTimeZone('Europe/Paris');

        return [$defaultUtc, $explicit];
    }
}

?>
-----
<?php

namespace App\Time;

use Carbon\CarbonTimeZone;

final class Factory
{
    public function make(): array
    {
        $defaultUtc = new CarbonTimeZone('UTC');
        $explicit = new CarbonTimeZone('Europe/Paris');

        return [$defaultUtc, $explicit];
    }
}

?>
