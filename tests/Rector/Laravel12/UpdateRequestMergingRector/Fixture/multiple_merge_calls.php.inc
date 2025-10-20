<?php

class FormController
{
    public function handleForm(Request $request)
    {
        // First mergeIfMissing call
        $request->mergeIfMissing(['created_at' => now()]);

        // Process some data
        $data = $request->validated();

        // Second mergeIfMissing call
        $request->mergeIfMissing([
            'updated_at' => now(),
            'user_id' => auth()->id()
        ]);

        return $data;
    }
}

?>
-----
<?php

class FormController
{
    public function handleForm(Request $request)
    {
        // First mergeIfMissing call
        $request->mergeIfMissing(['created_at' => now()]);

        // Process some data
        $data = $request->validated();

        // Second mergeIfMissing call
        $request->mergeIfMissing([
            'updated_at' => now(),
            'user_id' => auth()->id()
        ]);

        return $data;
    }
}

?>