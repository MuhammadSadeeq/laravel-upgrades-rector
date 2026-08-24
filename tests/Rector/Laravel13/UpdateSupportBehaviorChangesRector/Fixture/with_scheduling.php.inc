<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Foundation\Configuration\ApplicationBuilder;

function configure(ApplicationBuilder $app)
{
    $app->withScheduling(function () {
    });
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Foundation\Configuration\ApplicationBuilder;

function configure(ApplicationBuilder $app)
{
    // Laravel 13: withScheduling() registrations are now deferred until Schedule is resolved. Review bootstrap logic that relied on immediate registration timing.
    $app->withScheduling(function () {
    });
}

?>
