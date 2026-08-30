<?php

namespace App;

use Illuminate\Support\Facades\Schema;

function inspectEverySchema(): void
{
    $tables = Schema::getTables();
    $listing = Schema::getTableListing();
    $views = Schema::getViews();
    $types = Schema::getTypes();
}
