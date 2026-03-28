<?php

use Illuminate\Support\Facades\Schema;

class SchemaAnalyzer
{
    public function getSpecificSchemaData()
    {
        $tables = Schema::getTables('public');
        $views = Schema::getViews('admin');
        $types = Schema::getTypes('custom_schema');
        $tableListing = Schema::getTableListing('test_schema');

        return compact('tables', 'views', 'types', 'tableListing');
    }
}

?>
