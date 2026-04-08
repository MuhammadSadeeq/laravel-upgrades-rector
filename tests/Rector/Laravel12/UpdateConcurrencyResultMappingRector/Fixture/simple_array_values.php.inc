<?php

use Laravel\SerializableClosure\SerializableClosure;

function testSimpleArrayValues()
{
    Concurrency::run([
        // Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
        'first' => fn() => Http::get('https://api.example1.com'),
        'second' => fn() => Http::get('https://api.example2.com'),
        'third' => fn() => Http::get('https://api.example3.com'),
    ]);

    return true;
}

?>
-----
<?php

use Laravel\SerializableClosure\SerializableClosure;

function testSimpleArrayValues()
{
    Concurrency::run([
        // Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
        'first' => fn() => Http::get('https://api.example1.com'),
        'second' => fn() => Http::get('https://api.example2.com'),
        'third' => fn() => Http::get('https://api.example3.com'),
    ]);

    return true;
}

?>
