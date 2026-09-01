<?php

namespace App;

function inspectUnresolvedDoctrine($connection): void
{
    $connection->getDoctrineSchemaManager();
    $connection->registerDoctrineType('json', 'json');
    $connection->getDoctrineSomethingElse();
}
