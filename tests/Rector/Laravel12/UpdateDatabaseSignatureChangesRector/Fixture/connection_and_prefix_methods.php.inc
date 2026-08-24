<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseSignatureChangesRector;

use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use Illuminate\Database\Schema\Blueprint;

function inspect(Connection $connection, Blueprint $blueprint, Grammar $grammar)
{
    $connection->withTablePrefix($grammar);
    $blueprint->getPrefix();
    $grammar->getTablePrefix();
    $grammar->setTablePrefix('foo_');
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseSignatureChangesRector;

use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use Illuminate\Database\Schema\Blueprint;

function inspect(Connection $connection, Blueprint $blueprint, Grammar $grammar)
{
    // @laravel-upgrade db-signature-changes Connection::withTablePrefix() was removed; read the prefix from the Connection directly instead.
    $connection->withTablePrefix($grammar);
    // @laravel-upgrade db-signature-changes Blueprint::getPrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $blueprint->getPrefix();
    // @laravel-upgrade db-signature-changes Grammar::getTablePrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $grammar->getTablePrefix();
    // @laravel-upgrade db-signature-changes Grammar::setTablePrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $grammar->setTablePrefix('foo_');
}

?>
