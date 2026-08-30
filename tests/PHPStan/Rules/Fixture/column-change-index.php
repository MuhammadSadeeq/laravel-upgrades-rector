<?php

namespace App;

use Illuminate\Database\Schema\Blueprint;

function modifyIndexedBlueprint(Blueprint $table): void
{
    $table->string('email')->unique()->change();
}
