<?php

class NonValidationStringTest
{
    private string $type = 'image';

    public function getImageType()
    {
        $imageType = 'image';
        $format = 'image';

        return $imageType;
    }

    public function displayMessage()
    {
        echo 'Upload an image';
        $message = 'Please select an image file';

        return "The image field is required";
    }

    public function getMediaTypes()
    {
        return ['image', 'video', 'audio'];
    }
}

?>