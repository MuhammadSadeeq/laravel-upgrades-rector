<?php

namespace App\Fixtures\Laravel12\UpdateSchemaMethodsRector;

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

namespace App\Fixtures\Laravel12\UpdateSchemaMethodsRector;

use Illuminate\Support\Facades\Schema;

class MixedSchemaUsage
{
    public function analyze()
    {
        // @laravel-upgrade schema-inspection-methods This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $allTables = Schema::getTables();
        // @laravel-upgrade schema-inspection-methods This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $allViews = Schema::getViews();

        $publicTables = Schema::getTables('public');
        $testViews = Schema::getViews('test');

        Schema::create('new_table', function ($table) {
            $table->id();
        });

        // @laravel-upgrade schema-inspection-methods This method now returns results from all schemas by default. Pass a schema name to limit to a specific schema.
        $types = Schema::getTypes();
        // @laravel-upgrade schema-inspection-methods getTableListing() now returns schema-qualified table names from all schemas by default. Pass schema and schemaQualified arguments to preserve previous behavior.
        $listing = Schema::getTableListing();

        return compact('allTables', 'allViews', 'publicTables', 'testViews', 'types', 'listing');
    }
}

?>
