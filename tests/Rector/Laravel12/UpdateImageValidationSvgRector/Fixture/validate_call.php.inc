<?php

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

function store(Request $request)
{
    $request->validate([
        'photo' => 'image|required',
    ]);
}

?>
-----
<?php

namespace App\Fixtures\Laravel12\UpdateImageValidationSvgRector;

function store(Request $request)
{
    // @laravel-upgrade image-rule-svg the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
    $request->validate([
        'photo' => 'image|required',
    ]);
}

?>
