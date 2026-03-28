<?php

class ArrayKeyTest
{
    public function getData()
    {
        return [
            'image' => $this->product->image ? asset(Storage::url($this->product->image)) : '',
            'title' => 'Some title',
            'image_url' => url('/images/photo.jpg'),
        ];
    }

    public function getConfig()
    {
        $config = [
            'image' => 'default.png',
            'thumbnail' => 'thumb.jpg',
        ];

        return $config;
    }
}

?>