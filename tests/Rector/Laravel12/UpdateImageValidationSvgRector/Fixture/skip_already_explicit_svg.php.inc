<?php

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

use Illuminate\Validation\Rules\File;

class UploadRequest
{
    public function rules()
    {
        return [
            'photo1' => 'required|image:allow_svg',
            'photo2' => ['required', File::image(allowSvg: true)],
        ];
    }
}
