<?php

use Illuminate\Support\Facades\Schema;

$type = Schema::getColumnType('users', 'amount');

?>
-----
<?php

use Illuminate\Support\Facades\Schema;

// Laravel 11: Schema::getColumnType() now returns the actual column type, not the Doctrine DBAL equivalent. Review any type comparisons.
$type = Schema::getColumnType('users', 'amount');

?>
