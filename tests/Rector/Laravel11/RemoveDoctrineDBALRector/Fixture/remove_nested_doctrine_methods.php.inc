<?php

use Illuminate\Database\Schema\Builder;

class LegacyDoctrineUsage
{
    public function inspectDatabase($connection): array
    {
        $schemaManager = $connection->getDoctrineSchemaManager();
        $usingNativeSchemaOperations = $connection->usingNativeSchemaOperations();
        Builder::useNativeSchemaOperationsIfPossible();

        return [$schemaManager, $usingNativeSchemaOperations];
    }
}

?>
-----
<?php

use Illuminate\Database\Schema\Builder;

class LegacyDoctrineUsage
{
    public function inspectDatabase($connection): array
    {
        // @laravel-upgrade dbal: getDoctrineSchemaManager() was removed in Laravel 11 (low confidence). Doctrine DBAL is no longer required; migrate to native schema operations.
        $schemaManager = $connection->getDoctrineSchemaManager();
        $usingNativeSchemaOperations = $connection->usingNativeSchemaOperations();
        Builder::useNativeSchemaOperationsIfPossible();

        return [$schemaManager, $usingNativeSchemaOperations];
    }
}

?>
