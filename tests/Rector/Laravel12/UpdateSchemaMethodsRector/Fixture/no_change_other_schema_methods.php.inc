<?php

use Illuminate\Support\Facades\Schema;

class SchemaOperations
{
    public function performOperations()
    {
        // Should not change - different schema methods
        Schema::create('users', function ($table) {
            $table->id();
        });

        Schema::drop('old_table');
        Schema::hasTable('posts');
        Schema::hasColumn('users', 'email');

        $connection = Schema::getConnection();
        $columns = Schema::getColumnListing('users');

        return true;
    }
}

?>
-----
<?php

use Illuminate\Support\Facades\Schema;

class SchemaOperations
{
    public function performOperations()
    {
        // Should not change - different schema methods
        Schema::create('users', function ($table) {
            $table->id();
        });

        Schema::drop('old_table');
        Schema::hasTable('posts');
        Schema::hasColumn('users', 'email');

        $connection = Schema::getConnection();
        $columns = Schema::getColumnListing('users');

        return true;
    }
}

?>