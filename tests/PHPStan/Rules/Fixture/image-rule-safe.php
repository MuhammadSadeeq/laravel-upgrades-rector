<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

function validateSvgUpload(Request $request): void
{
    $request->validate([
        'photo' => 'required|image:allow_svg',
    ]);

    $validator->validate([
        'photo' => ['required', File::image(allowSvg: true)],
    ]);
}
