<?php

use Illuminate\Database\Schema\Blueprint;

class CreateAreasTable
{
    public function up()
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->polygon('boundary');
            $table->lineString('path');
            $table->timestamps();
        });
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Blueprint;

class CreateAreasTable
{
    public function up()
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->geometry('boundary');
            $table->geometry('path');
            $table->timestamps();
        });
    }
}

?>