<?php

class MimeTypeTest
{
    public function rules()
    {
        return [
            // Should NOT change 'image' in MIME types
            'file1' => 'required|mimes:image/jpeg,image/png',
            'file2' => 'mimes:image/gif,image/webp|max:2048',
            'file3' => ['required', 'mimes:image/jpeg,image/png'],
        ];
    }
}

?>