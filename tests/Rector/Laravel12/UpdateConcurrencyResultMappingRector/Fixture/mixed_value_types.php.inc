<?php

function testMixedValueTypes()
{
    Concurrency::run([
        42,
        'hello world',
        fn() => sleep(1),
    ]);
}

?>
-----
<?php

function testMixedValueTypes()
{
    // Laravel 12: Concurrency::run() now preserves associative array keys in results. Verify your code handles keyed results correctly.
    Concurrency::run([
        42,
        'hello world',
        fn() => sleep(1),
    ]);
}

?>
