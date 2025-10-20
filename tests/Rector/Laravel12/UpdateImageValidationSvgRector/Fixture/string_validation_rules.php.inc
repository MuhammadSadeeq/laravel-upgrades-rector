<?php

class FormRequest
{
    public function rules()
    {
        return [
            'photo' => 'required|image',
            'avatar' => 'nullable|image|max:2048',
            'banner' => 'image|dimensions:min_width=800',
            'thumbnail' => 'required|image',
        ];
    }
}

?>
-----
<?php

class FormRequest
{
    public function rules()
    {
        return [
            'photo' => 'required|image:allow_svg',
            'avatar' => 'nullable|image:allow_svg|max:2048',
            'banner' => 'image:allow_svg|dimensions:min_width=800',
            'thumbnail' => 'required|image:allow_svg',
        ];
    }
}

?>