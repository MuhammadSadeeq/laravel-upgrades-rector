<?php

namespace App\Fixtures\Laravel12\UpdateConcurrencyResultMappingRector;

function testSingleTask()
{
    Concurrency::run([
        fn() => Http::get('https://api.example.com/data'),
    ]);
}

?>
