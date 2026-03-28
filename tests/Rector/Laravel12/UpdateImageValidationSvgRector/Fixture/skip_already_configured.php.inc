<?php

class AlreadyConfiguredRequest
{
    public function rules()
    {
        return [
            // Should not change - already has explicit SVG configuration
            'photo1' => 'required|image:allow_svg',
            'photo2' => 'image:deny_svg|max:2048',
            'photo3' => ['image:allow_svg', 'required'],
            'photo4' => ['image:deny_svg', 'nullable'],

            // Should not change - different validation rules
            'email' => 'required|email',
            'name' => 'string|max:255',
            'file' => 'file|mimes:pdf,doc',
        ];
    }
}

?>