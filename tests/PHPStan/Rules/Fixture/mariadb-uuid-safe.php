<?php

namespace App;

final class UuidFactory
{
    public function uuid(string $name): string
    {
        return $name;
    }
}

function makeUuid(UuidFactory $factory): string
{
    return $factory->uuid('external_id');
}
