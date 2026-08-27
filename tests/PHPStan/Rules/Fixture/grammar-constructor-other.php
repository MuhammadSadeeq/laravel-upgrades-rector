<?php

namespace App;

use Illuminate\Database\Query\Grammars\MySqlGrammar;

function grammarWithArgument(object $connection): void
{
    new MySqlGrammar($connection);
}
