<?php

namespace App\Fixtures\Laravel13\UpdateContainerCallNullableDefaultsRector;

use Illuminate\Container\Container;

function resolveValue(Container $container)
{
    $container->call(function (?string $value = null) {
        return $value;
    });
}

?>
