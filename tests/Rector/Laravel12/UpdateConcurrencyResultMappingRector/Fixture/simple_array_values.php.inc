<?php

use Laravel\SerializableClosure\SerializableClosure;

function testSimpleArrayValues()
{
    // Non-associative arrays are not affected by the Laravel 12 change
    $results = Concurrency::run([
        fn() => Http::get('https://api.example1.com'),
        fn() => Http::get('https://api.example2.com'),
        fn() => Http::get('https://api.example3.com'),
    ]);

    return $results;
}

?>