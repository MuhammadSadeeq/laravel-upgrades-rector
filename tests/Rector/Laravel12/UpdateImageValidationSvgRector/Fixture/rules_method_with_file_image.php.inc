<?php

use Illuminate\Validation\Rules\File;

class UploadRequest
{
    public function rules()
    {
        return [
            'photo' => ['required', File::image()],
        ];
    }
}

?>
-----
<?php

use Illuminate\Validation\Rules\File;

class UploadRequest
{
    // @laravel-upgrade image-rule-svg the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
    public function rules()
    {
        return [
            'photo' => ['required', File::image()],
        ];
    }
}

?>
