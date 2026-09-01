<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

class DoctrineUser
{
    public function inspect($connection)
    {
        // Unresolved receiver + unmistakably Doctrine names are left for PHPStan.
        $connection->getDoctrineConnection();
        $connection->getDoctrineSchemaManager();
        $connection->getDoctrineColumn('users', 'name');
        $connection->registerDoctrineType('custom', 'name', 'type');
    }
}
