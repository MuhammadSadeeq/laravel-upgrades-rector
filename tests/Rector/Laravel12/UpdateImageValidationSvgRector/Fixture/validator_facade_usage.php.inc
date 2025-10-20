<?php

use Illuminate\Support\Facades\Validator;

class FileController
{
    public function upload(Request $request)
    {
        $validator1 = Validator::make($request->all(), [
            'file' => 'required|image',
        ]);

        $validator2 = Validator::make($data, [
            'avatar' => ['nullable', 'image'],
            'banner' => 'image|max:5120',
        ]);

        return [$validator1, $validator2];
    }
}

?>
-----
<?php

use Illuminate\Support\Facades\Validator;

class FileController
{
    public function upload(Request $request)
    {
        $validator1 = Validator::make($request->all(), [
            'file' => 'required|image:allow_svg',
        ]);

        $validator2 = Validator::make($data, [
            'avatar' => ['nullable', 'image:allow_svg'],
            'banner' => 'image:allow_svg|max:5120',
        ]);

        return [$validator1, $validator2];
    }
}

?>