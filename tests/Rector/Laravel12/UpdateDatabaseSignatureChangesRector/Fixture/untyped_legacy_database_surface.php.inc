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
    // Laravel 12: database constructor and grammar APIs changed. Grammar constructors now require a Connection instance, and setConnection() has been removed.
    $grammar = new MySqlGrammar();
    // Laravel 12: database constructor and grammar APIs changed. Grammar::setConnection() was removed; inject the Connection via the constructor instead.
    $grammar->setConnection($connection);
    // Laravel 12: database constructor and grammar APIs changed. Connection::withTablePrefix() was removed; read the prefix from the Connection directly instead.
    $connection->withTablePrefix($grammar);
    // Laravel 12: database constructor and grammar APIs changed. Blueprint::getPrefix() is deprecated; retrieve the table prefix from the Connection instead.
    $blueprint->getPrefix();
}

?>
