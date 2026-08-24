<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Str;

Str::createUuidsUsing(fn () => 'fixed');

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateSupportBehaviorChangesRector;

use Illuminate\Support\Str;

// Laravel 13: custom Str factories are reset between tests. Re-register UUID / ULID / random-string factories in each relevant test or setup hook.
Str::createUuidsUsing(fn () => 'fixed');

?>
