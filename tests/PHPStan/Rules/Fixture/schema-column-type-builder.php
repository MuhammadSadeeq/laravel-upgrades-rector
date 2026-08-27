<?php

namespace App;

use Illuminate\Database\Schema\Builder;

function inspectSchemaBuilder(Builder $schema): void
{
    $type = $schema->getColumnType('users', 'name');
}
