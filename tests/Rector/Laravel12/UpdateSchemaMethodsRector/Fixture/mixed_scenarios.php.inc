<?php

use Illuminate\Support\Facades\Schema;

class MixedSchemaUsage
{
    public function analyze()
    {
        $allTables = Schema::getTables();
        $allViews = Schema::getViews();

        $publicTables = Schema::getTables('public');
        $testViews = Schema::getViews('test');

        Schema::create('new_table', function ($table) {
            $table->id();
        });

        $types = Schema::getTypes();
        $listing = Schema::getTableListing();

        return compact('allTables', 'allViews', 'publicTables', 'testViews', 'types', 'listing');
    }
}

?>
-----
<?php

use Illuminate\Support\Facades\Schema;

class MixedSchemaUsage
{
    public function analyze()
    {
        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $allTables = Schema::getTables();
        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $allViews = Schema::getViews();

        $publicTables = Schema::getTables('public');
        $testViews = Schema::getViews('test');

        Schema::create('new_table', function ($table) {
            $table->id();
        });

        // Laravel 12: This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $types = Schema::getTypes();
        // Laravel 12: getTableListing() now returns schema-qualified table names from all schemas by default. Pass schema and schemaQualified arguments to preserve previous behavior.
        $listing = Schema::getTableListing();

        return compact('allTables', 'allViews', 'publicTables', 'testViews', 'types', 'listing');
    }
}

?>
