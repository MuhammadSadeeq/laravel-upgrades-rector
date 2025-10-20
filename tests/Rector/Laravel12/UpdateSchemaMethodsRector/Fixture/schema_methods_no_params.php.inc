<?php

use Illuminate\Support\Facades\Schema;

class DatabaseInspector
{
    public function inspectDatabase()
    {
        // These methods now return multi-schema results in Laravel 12
        $tables = Schema::getTables();
        $views = Schema::getViews();
        $types = Schema::getTypes();
        $tableListing = Schema::getTableListing();

        return compact('tables', 'views', 'types', 'tableListing');
    }
}

?>
-----
<?php

use Illuminate\Support\Facades\Schema;

class DatabaseInspector
{
    public function inspectDatabase()
    {
        // These methods now return multi-schema results in Laravel 12
        $tables = Schema::getTables();
        $views = Schema::getViews();
        $types = Schema::getTypes();
        $tableListing = Schema::getTableListing();

        return compact('tables', 'views', 'types', 'tableListing');
    }
}

?>