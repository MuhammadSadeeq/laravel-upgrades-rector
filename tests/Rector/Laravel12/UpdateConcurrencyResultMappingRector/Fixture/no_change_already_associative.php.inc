<?php

function testNoChangeAlreadyAssociative()
{
    // Laravel 12: Associative arrays now preserve keys in results
    $results = Concurrency::run([
        'fetch_users' => fn() => User::all(),
        'fetch_posts' => fn() => Post::all(),
        'fetch_comments' => fn() => Comment::all(),
    ]);

    return $results;
}

?>