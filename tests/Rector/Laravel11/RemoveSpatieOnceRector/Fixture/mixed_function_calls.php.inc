<?php

class MixedExample
{
    public function example()
    {
        // These should be flagged
        $spatieResult = spatieCache();
        $onceResult = OnceHelper();

        // These should not be flagged
        $regularResult = helper();
        $cacheResult = cache();
    }
}

?>
-----
<?php

class MixedExample
{
    public function example()
    {
        // These should be flagged
        $spatieResult = spatieCache();
        $onceResult = OnceHelper();

        // These should not be flagged
        $regularResult = helper();
        $cacheResult = cache();
    }
}

?>