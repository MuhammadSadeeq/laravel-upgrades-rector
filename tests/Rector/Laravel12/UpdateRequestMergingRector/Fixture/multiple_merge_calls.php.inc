<?php

use Illuminate\Http\Request;

class FormController
{
    public function handleForm(Request $request)
    {
        $request->mergeIfMissing(['created_at' => now()]);

        $data = $request->validated();

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

use Illuminate\Http\Request;

class FormController
{
    public function handleForm(Request $request)
    {
        // Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $request->mergeIfMissing(['created_at' => now()]);

        $data = $request->validated();

        // Laravel 12: mergeIfMissing() now supports nested array merging with dot notation. This may change behavior if you were relying on shallow merging.
        $request->mergeIfMissing([
            'updated_at' => now(),
            'user_id' => auth()->id()
        ]);

        return $data;
    }
}

?>
