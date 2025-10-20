<?php

function testNoChangeNonConcurrencyCalls()
{
    // Should not change - not Concurrency::run
    $other = SomeClass::run([1, 2, 3]);

    // Should not change - different method
    $results = Concurrency::execute([
        fn() => Http::get('url1'),
        fn() => Http::get('url2'),
    ]);

    return [$other, $results];
}

?>
-----
<?php

function testNoChangeNonConcurrencyCalls()
{
    // Should not change - not Concurrency::run
    $other = SomeClass::run([1, 2, 3]);

    // Should not change - different method
    $results = Concurrency::execute([
        fn() => Http::get('url1'),
        fn() => Http::get('url2'),
    ]);

    return [$other, $results];
}

?>