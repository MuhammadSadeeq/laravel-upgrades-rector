<?php

class UploadRequest
{
    public function rules()
    {
        return [
            'photo' => 'required_without:image|exists:images,image',
        ];
    }
}
