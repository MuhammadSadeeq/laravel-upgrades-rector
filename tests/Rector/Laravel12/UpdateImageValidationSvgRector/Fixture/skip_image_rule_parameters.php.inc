<?php

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

class UploadRequest
{
    public function rules()
    {
        return [
            'photo' => 'required_without:image|exists:images,image',
        ];
    }
}
