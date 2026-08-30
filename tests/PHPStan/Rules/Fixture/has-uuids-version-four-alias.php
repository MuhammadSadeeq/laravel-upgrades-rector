<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids as HasUuids;
use Illuminate\Database\Eloquent\Model;

final class HasUuidsVersionFourAlias extends Model
{
    use HasUuids;
}
