<?php

namespace App\Fixtures\Laravel12\UpdateSchemaMethodsRector;

use Illuminate\Support\Facades\Schema;

class SchemaOperations
{
    public function performOperations()
    {
        Schema::create('users', function ($table) {
            $table->id();
        });

        Schema::drop('old_table');
        Schema::hasTable('posts');
        Schema::hasColumn('users', 'email');
    }
}

?>
