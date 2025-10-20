<?php

use Illuminate\Support\Facades\Schema;

class MixedSchemaUsage
{
    public function analyze()
    {
        // Should get comments - no parameters
        $allTables = Schema::getTables();
        $allViews = Schema::getViews();

        // Should not change - has parameters
        $publicTables = Schema::getTables('public');
        $testViews = Schema::getViews('test');

        // Should not change - different methods
        Schema::create('new_table', function ($table) {
            $table->id();
        });

        // Should get comments - no parameters
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
        // Should get comments - no parameters
        $allTables = Schema::getTables();
        $allViews = Schema::getViews();

        // Should not change - has parameters
        $publicTables = Schema::getTables('public');
        $testViews = Schema::getViews('test');

        // Should not change - different methods
        Schema::create('new_table', function ($table) {
            $table->id();
        });

        // Should get comments - no parameters
        $types = Schema::getTypes();
        $listing = Schema::getTableListing();

        return compact('allTables', 'allViews', 'publicTables', 'testViews', 'types', 'listing');
    }
}

?>