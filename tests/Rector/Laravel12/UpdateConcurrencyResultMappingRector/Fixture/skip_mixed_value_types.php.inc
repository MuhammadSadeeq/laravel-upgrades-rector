<?php

namespace App\Fixtures\Laravel12\UpdateConcurrencyResultMappingRector;

function testMixedValueTypes()
{
    Concurrency::run([
        42,
        'hello world',
        fn() => sleep(1),
    ]);
}

?>
