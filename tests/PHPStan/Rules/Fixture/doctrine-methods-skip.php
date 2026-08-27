<?php

namespace App;

class CustomConnection
{
    public function getDoctrineColumn(): void {}
}

function inspectCustomDoctrine(CustomConnection $connection): void
{
    $connection->getDoctrineColumn();
}
