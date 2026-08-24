<?php

namespace App\Fixtures\Laravel11\UpdateSqliteVersionRector;

return [
    'driver' => 'sqlite',
];

?>
-----
<?php

namespace App\Fixtures\Laravel11\UpdateSqliteVersionRector;

return [
    // Laravel 11: SQLite 3.26.0 or greater is required. Verify the runtime SQLite version before upgrading.
    'driver' => 'sqlite',
];

?>
