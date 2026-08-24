<?php

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

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

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

function validateUpload(array $data)
{
    // @laravel-upgrade image-rule-svg the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
    $validator = Validator::make($data, [
        'photo' => ['required', File::image()],
    ]);

    return $validator;
}

?>
