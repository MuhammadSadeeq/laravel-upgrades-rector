<?php

function testAlreadyCommented()
{
    // Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
    Concurrency::run([
        'fetch_users' => fn() => User::all(),
        'fetch_posts' => fn() => Post::all(),
    ]);
}

?>
