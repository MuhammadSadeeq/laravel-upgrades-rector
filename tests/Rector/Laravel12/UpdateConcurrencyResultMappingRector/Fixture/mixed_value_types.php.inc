<?php

function testMixedValueTypes()
{
    // Non-associative arrays are not affected
    return Concurrency::run([
        42,
        'hello world',
        fn() => sleep(1),
    ]);
}

?>