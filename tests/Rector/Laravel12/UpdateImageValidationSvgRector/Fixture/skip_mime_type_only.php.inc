<?php

class UploadRequest
{
    public function rules()
    {
        return [
            'photo' => 'required|mimes:image/jpeg,image/png',
        ];
    }
}
