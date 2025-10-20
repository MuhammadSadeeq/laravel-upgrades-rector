<?php

class ChainedMethodsController
{
    public function update(Request $request)
    {
        $result = $request
            ->mergeIfMissing(['default' => 'value'])
            ->validate(['name' => 'required']);

        $another = $request->mergeIfMissing([
            'metadata' => []
        ])->all();

        return [$result, $another];
    }
}

?>
-----
<?php

class ChainedMethodsController
{
    public function update(Request $request)
    {
        $result = $request
            ->mergeIfMissing(['default' => 'value'])
            ->validate(['name' => 'required']);

        $another = $request->mergeIfMissing([
            'metadata' => []
        ])->all();

        return [$result, $another];
    }
}

?>