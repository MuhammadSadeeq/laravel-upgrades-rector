<?php

class RequestController
{
    public function process(Request $request)
    {
        // Should not change - different methods
        $request->merge(['data' => 'value']);
        $request->replace(['new' => 'data']);
        $request->offsetSet('key', 'value');

        // Should not change - different objects
        $collection = collect()->mergeIfMissing(['item']);
        $array = array_merge_recursive($a, $b);

        return $request->all();
    }
}

?>
-----
<?php

class RequestController
{
    public function process(Request $request)
    {
        // Should not change - different methods
        $request->merge(['data' => 'value']);
        $request->replace(['new' => 'data']);
        $request->offsetSet('key', 'value');

        // Should not change - different objects
        $collection = collect()->mergeIfMissing(['item']);
        $array = array_merge_recursive($a, $b);

        return $request->all();
    }
}

?>