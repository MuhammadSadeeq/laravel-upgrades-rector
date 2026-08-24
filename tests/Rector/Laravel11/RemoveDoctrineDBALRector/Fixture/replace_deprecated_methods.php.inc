<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

use Illuminate\Support\Facades\Schema;

class SchemaExample
{
    public function example()
    {
        $tables = Schema::getAllTables();
        $views = Schema::getAllViews();
        $types = Schema::getAllTypes();

        // These should not be changed
        $columns = Schema::getColumns('users');
        $indexes = Schema::getIndexes('users');
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

use Illuminate\Support\Facades\Schema;

class SchemaExample
{
    public function example()
    {
        $tables = Schema::getTables();
        $views = Schema::getViews();
        $types = Schema::getTypes();

        // These should not be changed
        $columns = Schema::getColumns('users');
        $indexes = Schema::getIndexes('users');
    }
}

?>
