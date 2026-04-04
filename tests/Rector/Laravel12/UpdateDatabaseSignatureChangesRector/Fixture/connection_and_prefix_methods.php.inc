<?php

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

use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use Illuminate\Database\Schema\Blueprint;

function inspect(Connection $connection, Blueprint $blueprint, Grammar $grammar)
{
    // Laravel 12: database constructor and grammar APIs changed. Connection::withTablePrefix() was removed; read the prefix from the Connection directly instead.
    $connection->withTablePrefix($grammar);
    // Laravel 12: database constructor and grammar APIs changed. Blueprint::getPrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $blueprint->getPrefix();
    // Laravel 12: database constructor and grammar APIs changed. Grammar::getTablePrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $grammar->getTablePrefix();
    // Laravel 12: database constructor and grammar APIs changed. Grammar::setTablePrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $grammar->setTablePrefix('foo_');
}

?>
