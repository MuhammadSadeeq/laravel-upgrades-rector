<?php

namespace App;

function inspectUnresolvedDoctrine($connection): void
{
    $connection->getDoctrineSchemaManager();
}
