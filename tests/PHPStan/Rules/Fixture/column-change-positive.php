<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function modifyBlueprint(Blueprint $table): void
{
    $table->string('name')->change();
}
