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
        // Laravel 11: getDoctrineSchemaManager() has been removed. Doctrine DBAL is no longer required.
        $schemaManager = $connection->getDoctrineSchemaManager();
        // Laravel 11: usingNativeSchemaOperations() has been removed. Doctrine DBAL is no longer required.
        $usingNativeSchemaOperations = $connection->usingNativeSchemaOperations();
        // Laravel 11: useNativeSchemaOperationsIfPossible() has been removed. Doctrine DBAL is no longer required.
        Builder::useNativeSchemaOperationsIfPossible();

        return [$schemaManager, $usingNativeSchemaOperations];
    }
}

?>
