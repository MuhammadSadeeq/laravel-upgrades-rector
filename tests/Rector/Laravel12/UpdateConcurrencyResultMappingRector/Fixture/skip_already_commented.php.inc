<?php

namespace App\Fixtures\Laravel12\UpdateConcurrencyResultMappingRector;

function testAlreadyCommented()
{
    Concurrency::run([
        // @laravel-upgrade concurrency-keyed-results Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
        'fetch_users' => fn() => User::all(),
        'fetch_posts' => fn() => Post::all(),
    ]);
}

?>
