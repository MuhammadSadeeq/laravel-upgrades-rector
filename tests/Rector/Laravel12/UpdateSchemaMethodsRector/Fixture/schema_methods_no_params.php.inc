<?php

namespace App\Fixtures\Laravel12\UpdateSchemaMethodsRector;

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

namespace App\Fixtures\Laravel12\UpdateSchemaMethodsRector;

use Illuminate\Support\Facades\Schema;

class DatabaseInspector
{
    public function inspectDatabase()
    {
        // @laravel-upgrade schema-inspection-methods This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $tables = Schema::getTables();
        // @laravel-upgrade schema-inspection-methods This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $views = Schema::getViews();
        // @laravel-upgrade schema-inspection-methods This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $types = Schema::getTypes();
        // @laravel-upgrade schema-inspection-methods getTableListing() now returns schema-qualified table names from all schemas by default. Pass schema and schemaQualified arguments to preserve previous behavior.
        $tableListing = Schema::getTableListing();

        return compact('tables', 'views', 'types', 'tableListing');
    }
}

?>
