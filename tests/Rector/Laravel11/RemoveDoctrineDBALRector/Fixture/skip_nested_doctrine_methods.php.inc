<?php

namespace App\Fixtures\Laravel11\RemoveDoctrineDBALRector;

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
