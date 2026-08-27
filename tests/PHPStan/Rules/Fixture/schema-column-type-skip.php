<?php

namespace App;

function inspectOtherSchema(object $schema): void
{
    $schema->getColumnType('users', 'name');
}
