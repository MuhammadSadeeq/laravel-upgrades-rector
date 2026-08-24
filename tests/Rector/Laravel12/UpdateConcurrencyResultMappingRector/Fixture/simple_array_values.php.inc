<?php

namespace App\Fixtures\Laravel12\UpdateConcurrencyResultMappingRector;

use Illuminate\Support\Facades\Concurrency;
use Laravel\SerializableClosure\SerializableClosure;

function testSimpleArrayValues()
{
    Concurrency::run([
        'first' => fn() => Http::get('https://api.example1.com'),
        'second' => fn() => Http::get('https://api.example2.com'),
        'third' => fn() => Http::get('https://api.example3.com'),
    ]);

    return true;
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateConcurrencyResultMappingRector;

use Illuminate\Support\Facades\Concurrency;
use Laravel\SerializableClosure\SerializableClosure;

function testSimpleArrayValues()
{
    Concurrency::run([
        // @laravel-upgrade concurrency-keyed-results Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
        'first' => fn() => Http::get('https://api.example1.com'),
        'second' => fn() => Http::get('https://api.example2.com'),
        'third' => fn() => Http::get('https://api.example3.com'),
    ]);

    return true;
}

?>
