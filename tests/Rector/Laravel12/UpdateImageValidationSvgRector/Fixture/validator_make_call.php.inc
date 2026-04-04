<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

function validateUpload(array $data)
{
    $validator = Validator::make($data, [
        'photo' => ['required', File::image()],
    ]);

    return $validator;
}

?>
-----
<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

function validateUpload(array $data)
{
    // Laravel 12: the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
    $validator = Validator::make($data, [
        'photo' => ['required', File::image()],
    ]);

    return $validator;
}

?>
