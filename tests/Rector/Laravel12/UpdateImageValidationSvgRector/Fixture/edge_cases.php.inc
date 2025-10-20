<?php

class EdgeCasesTest
{
    public function rules()
    {
        return [
            // Edge case: image at different positions
            'start' => 'image|required',
            'middle' => 'nullable|image|max:2048',
            'end' => 'required|mimes:jpg,png|image',

            // Edge case: multiple images in one rule (shouldn't happen but test anyway)
            'multi' => 'image|sometimes|image',

            // Edge case: image-like but not image
            'not_image1' => 'required|imaginary',
            'not_image2' => 'string_with_image_word',
            'not_image3' => 'required|mimes:image/jpeg',
        ];
    }
}

?>
-----
<?php

class EdgeCasesTest
{
    public function rules()
    {
        return [
            // Edge case: image at different positions
            'start' => 'image:allow_svg|required',
            'middle' => 'nullable|image:allow_svg|max:2048',
            'end' => 'required|mimes:jpg,png|image:allow_svg',

            // Edge case: multiple images in one rule (shouldn't happen but test anyway)
            'multi' => 'image:allow_svg|sometimes|image:allow_svg',

            // Edge case: image-like but not image
            'not_image1' => 'required|imaginary',
            'not_image2' => 'string_with_image_word',
            'not_image3' => 'required|mimes:image:allow_svg/jpeg',
        ];
    }
}

?>