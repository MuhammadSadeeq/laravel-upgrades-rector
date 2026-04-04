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
