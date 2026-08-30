<?php

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules\Fixture;

use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Facades\Schema;

function inspectConnectionSchema(): void
{
    $tables = Schema::connection('tenant')->getTables();
    $other = Schema::getTableListing(schema: 'main');
}

function inspectTypedBuilder(SchemaBuilder $builder): void
{
    $tables = $builder->getTables();
    $listing = $builder->getTableListing();
    $views = $builder->getViews('main');
}
