<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Js;

function renderJs(mixed $payload)
{
    Js::from($payload);
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Js;

function renderJs(mixed $payload)
{
    // Laravel 13: Js::from() now uses unescaped Unicode by default. Update assertions that expected escaped Unicode sequences.
    Js::from($payload);
}

?>
