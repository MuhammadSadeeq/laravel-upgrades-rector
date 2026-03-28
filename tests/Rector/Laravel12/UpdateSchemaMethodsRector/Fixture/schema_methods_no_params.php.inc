<?php

use Illuminate\Support\Facades\Schema;

class DatabaseInspector
{
    public function inspectDatabase()
    {
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
        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $tables = Schema::getTables();
        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $views = Schema::getViews();
        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $types = Schema::getTypes();
        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $tableListing = Schema::getTableListing();

        return compact('tables', 'views', 'types', 'tableListing');
    }
}

?>
