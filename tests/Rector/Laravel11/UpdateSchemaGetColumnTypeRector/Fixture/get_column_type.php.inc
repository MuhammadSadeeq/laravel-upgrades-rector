<?php

use Illuminate\Support\Facades\Schema;

$type = Schema::getColumnType('users', 'amount');

?>
-----
<?php

use Illuminate\Support\Facades\Schema;

// @laravel-upgrade schema-get-column-type Schema::getColumnType() now returns the actual column type, not the Doctrine DBAL equivalent. Review any type comparisons.
$type = Schema::getColumnType('users', 'amount');

?>
