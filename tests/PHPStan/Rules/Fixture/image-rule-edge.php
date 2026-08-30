<?php

namespace App;

function validateUnrelatedValues($validator): void
{
    $validator->mergeIfMissing([
        'photo' => 'required|image',
    ]);

    $validator->validate([
        'image' => 'required',
        'description' => 'required|images',
        'caption' => 'image:strict',
    ]);

    $validator->validate([
        'photo' => 'required|image',
    ]);

    $validator->other([
        'photo' => 'required|image',
    ]);
}
