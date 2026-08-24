<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

class DoctrineUser
{
    public function inspect($connection)
    {
        // Unresolved receiver + unmistakably Doctrine names → low-confidence advisories.
        $connection->getDoctrineConnection();
        $connection->getDoctrineSchemaManager();
        $connection->getDoctrineColumn('users', 'name');
        $connection->registerDoctrineType('custom', 'name', 'type');
    }
}

?>
-----
<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

class DoctrineUser
{
    public function inspect($connection)
    {
        // Unresolved receiver + unmistakably Doctrine names → low-confidence advisories.
        // @laravel-upgrade dbal: getDoctrineConnection() was removed in Laravel 11 (low confidence). Doctrine DBAL is no longer required; migrate to native schema operations.
        $connection->getDoctrineConnection();
        // @laravel-upgrade dbal: getDoctrineSchemaManager() was removed in Laravel 11 (low confidence). Doctrine DBAL is no longer required; migrate to native schema operations.
        $connection->getDoctrineSchemaManager();
        // @laravel-upgrade dbal: getDoctrineColumn() was removed in Laravel 11 (low confidence). Doctrine DBAL is no longer required; migrate to native schema operations.
        $connection->getDoctrineColumn('users', 'name');
        // @laravel-upgrade dbal: registerDoctrineType() was removed in Laravel 11 (low confidence). Doctrine DBAL is no longer required; migrate to native schema operations.
        $connection->registerDoctrineType('custom', 'name', 'type');
    }
}

?>
