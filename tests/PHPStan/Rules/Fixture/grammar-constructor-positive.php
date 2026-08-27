<?php

namespace App;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

function grammarWithoutConnection(): void
{
    new MySqlGrammar;
}

function grammarWithConnection(Connection $connection): void
{
    new MySqlGrammar($connection);
}
