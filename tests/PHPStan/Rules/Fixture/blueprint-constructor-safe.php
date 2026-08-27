<?php

namespace App;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

function blueprintHasConnection(Connection $connection, string $table): void
{
    new Blueprint($connection, $table);
}

function unrelatedConstructor(): void
{
    new \stdClass;
}
