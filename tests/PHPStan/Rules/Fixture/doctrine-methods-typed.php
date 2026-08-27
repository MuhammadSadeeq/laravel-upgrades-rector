<?php

namespace App;

use Illuminate\Database\Connection;

function inspectTypedDoctrine(Connection $connection): void
{
    $connection->getDoctrineColumn('users', 'name');
}
