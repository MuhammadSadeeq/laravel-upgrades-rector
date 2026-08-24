<?php

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

class UploadRequest
{
    public function rules()
    {
        return [
            'photo' => 'required|mimes:image/jpeg,image/png',
        ];
    }
}
