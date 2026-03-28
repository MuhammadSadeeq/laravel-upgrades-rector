<?php

use App\Models\User;

function testNonCarbonStaticCalls()
{
    // These should NOT be changed - not Carbon classes
    $result1 = User::createFromTimestamp(123);
    $result2 = SomeClass::minValue();

    return [$result1, $result2];
}

?>
