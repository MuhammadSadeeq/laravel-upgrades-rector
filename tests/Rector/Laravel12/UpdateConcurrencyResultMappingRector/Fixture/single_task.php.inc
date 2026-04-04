<?php

function testSingleTask()
{
    Concurrency::run([
        fn() => Http::get('https://api.example.com/data'),
    ]);
}

?>
