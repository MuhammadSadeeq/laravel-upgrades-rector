<?php

function testSingleTask()
{
    // Non-associative arrays are not affected
    $result = Concurrency::run([
        fn() => Http::get('https://api.example.com/data'),
    ]);

    return $result;
}

?>