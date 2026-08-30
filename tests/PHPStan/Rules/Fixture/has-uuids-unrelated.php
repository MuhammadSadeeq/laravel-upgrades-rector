<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Illuminate\Database\Eloquent\Model;

trait UsesUuidValue {}

final class HasUuidsUnrelated extends Model
{
    use UsesUuidValue;
}
