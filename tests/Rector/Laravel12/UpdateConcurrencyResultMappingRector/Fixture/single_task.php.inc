<?php

function testSingleTask()
{
    Concurrency::run([
        fn() => Http::get('https://api.example.com/data'),
    ]);
}

?>
-----
<?php

function testSingleTask()
{
    // Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
    Concurrency::run([
        fn() => Http::get('https://api.example.com/data'),
    ]);
}

?>
