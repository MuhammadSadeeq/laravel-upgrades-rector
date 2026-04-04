<?php

function store(Request $request)
{
    $request->validate([
        'photo' => 'image|required',
    ]);
}

?>
-----
<?php

function store(Request $request)
{
    // Laravel 12: the image validation rule no longer allows SVG files by default. Add image:allow_svg or File::image(allowSvg: true) if your application relied on SVG uploads.
    $request->validate([
        'photo' => 'image|required',
    ]);
}

?>
