<?php

namespace App\Fixtures\Laravel12\UpdateConcurrencyResultMappingRector;

function testNoChangeNonConcurrencyCalls()
{
    // Should not change - not Concurrency::run
    SomeClass::run([1, 2, 3]);

    // Should not change - different method
    Concurrency::execute([
        fn() => Http::get('url1'),
        fn() => Http::get('url2'),
    ]);
}

?>
