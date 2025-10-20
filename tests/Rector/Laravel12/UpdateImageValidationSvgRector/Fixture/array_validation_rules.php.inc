<?php

function getValidationRules()
{
    return [
        'profile_image' => ['required', 'image'],
        'cover_photo' => ['nullable', 'image', 'max:5120'],
        'gallery_image' => ['image', 'dimensions:ratio=16/9'],
        'logo' => ['required', 'image'],
    ];
}

?>
-----
<?php

function getValidationRules()
{
    return [
        'profile_image' => ['required', 'image:allow_svg'],
        'cover_photo' => ['nullable', 'image:allow_svg', 'max:5120'],
        'gallery_image' => ['image:allow_svg', 'dimensions:ratio=16/9'],
        'logo' => ['required', 'image:allow_svg'],
    ];
}

?>