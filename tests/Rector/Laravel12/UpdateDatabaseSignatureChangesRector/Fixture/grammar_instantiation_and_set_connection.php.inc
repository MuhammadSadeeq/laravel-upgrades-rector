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
    // @laravel-upgrade db-signature-changes Grammar constructors now require a Connection instance, and setConnection() has been removed.
    $grammar = new MySqlGrammar();
    // @laravel-upgrade db-signature-changes Grammar::setConnection() was removed; inject the Connection via the constructor instead.
    $grammar->setConnection($connection);
}

?>
