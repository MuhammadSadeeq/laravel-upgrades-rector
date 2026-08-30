<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

function validateImageUpload(Request $request): void
{
    $request->validate([
        'photo' => 'required|image',
    ]);

    Validator::make([], [
        'photo' => 'required|image',
    ]);

    File::image();
    File::image(false);
    File::image(allowSvg: false);
}
