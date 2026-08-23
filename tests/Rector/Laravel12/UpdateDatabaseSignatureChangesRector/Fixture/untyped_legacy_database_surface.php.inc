<?php

use Illuminate\Database\Query\Grammars\MySqlGrammar;

function legacyDatabaseSurface($connection, $blueprint)
{
    $grammar = new MySqlGrammar();
    $grammar->setConnection($connection);
    $connection->withTablePrefix($grammar);
    $blueprint->getPrefix();
}

?>
-----
<?php

use Illuminate\Database\Query\Grammars\MySqlGrammar;

function legacyDatabaseSurface($connection, $blueprint)
{
    // @laravel-upgrade db-signature-changes Grammar constructors now require a Connection instance, and setConnection() has been removed.
    $grammar = new MySqlGrammar();
    // @laravel-upgrade db-signature-changes Grammar::setConnection() was removed; inject the Connection via the constructor instead.
    $grammar->setConnection($connection);
    // @laravel-upgrade db-signature-changes Connection::withTablePrefix() was removed; read the prefix from the Connection directly instead.
    $connection->withTablePrefix($grammar);
    // @laravel-upgrade db-signature-changes Blueprint::getPrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $blueprint->getPrefix();
}

?>
