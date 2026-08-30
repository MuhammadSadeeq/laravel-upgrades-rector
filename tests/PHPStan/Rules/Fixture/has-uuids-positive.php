<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class HasUuidsPositive extends Model
{
    use HasUuids;
}
