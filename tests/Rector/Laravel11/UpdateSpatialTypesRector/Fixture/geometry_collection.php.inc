<?php

use Illuminate\Database\Schema\Blueprint;

class CreateCollectionsTable
{
    public function up()
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->geometryCollection('data');
            $table->timestamps();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateCollectionsTable
{
    public function up()
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->geometry('data', subtype: 'geometryCollection');
            $table->timestamps();
        });
    }
}

?>