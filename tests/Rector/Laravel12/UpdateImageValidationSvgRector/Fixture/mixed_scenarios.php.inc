<?php

class MixedScenariosRequest
{
    public function rules()
    {
        return [
            // Should change - standalone image rule
            'upload1' => 'required|image',

            // Should not change - already configured
            'upload2' => 'required|image:allow_svg',
            'upload3' => 'image:deny_svg|max:1024',

            // Should change - image within pipe rules
            'upload4' => 'nullable|image|mimes:jpg,png',

            // Mixed array and string formats
            'upload5' => ['required', 'image'],
            'upload6' => ['image:allow_svg', 'max:2048'],
        ];
    }
}

?>
-----
<?php

class MixedScenariosRequest
{
    public function rules()
    {
        return [
            // Should change - standalone image rule
            'upload1' => 'required|image:allow_svg',

            // Should not change - already configured
            'upload2' => 'required|image:allow_svg',
            'upload3' => 'image:deny_svg|max:1024',

            // Should change - image within pipe rules
            'upload4' => 'nullable|image:allow_svg|mimes:jpg,png',

            // Mixed array and string formats
            'upload5' => ['required', 'image:allow_svg'],
            'upload6' => ['image:allow_svg', 'max:2048'],
        ];
    }
}

?>