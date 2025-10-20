<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('votes')->nullable()->change();
            $table->string('name', 100)->change();
            $table->decimal('price', 8, 2)->unsigned()->default(0)->change();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('votes')->nullable()->change();
            $table->string('name', 100)->change();
            $table->decimal('price', 8, 2)->unsigned()->default(0)->change();
        });
    }
}

?>