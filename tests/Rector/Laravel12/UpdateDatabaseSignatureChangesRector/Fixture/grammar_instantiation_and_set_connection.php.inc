<?php

use Illuminate\Database\Query\Grammars\MySqlGrammar;

function configureGrammar($connection)
{
    $grammar = new MySqlGrammar();
    $grammar->setConnection($connection);
}

?>
-----
<?php

use Illuminate\Database\Query\Grammars\MySqlGrammar;

function configureGrammar($connection)
{
    // Laravel 12: database constructor and grammar APIs changed. Grammar constructors now require a Connection instance, and setConnection() has been removed.
    $grammar = new MySqlGrammar();
    // Laravel 12: database constructor and grammar APIs changed. Grammar::setConnection() was removed; inject the Connection via the constructor instead.
    $grammar->setConnection($connection);
}

?>
