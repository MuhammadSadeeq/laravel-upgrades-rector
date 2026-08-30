<?php

namespace App;

use Illuminate\Support\Facades\Schema;

function inspectOneSchema(): void
{
    $tables = Schema::getTables(schema: 'main');
    $listing = Schema::getTableListing(schema: 'main');
    $views = Schema::getViews('main');
    $types = Schema::getTypes(schema: ['main', 'blog']);
}
