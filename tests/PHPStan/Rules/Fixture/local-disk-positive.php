<?php

namespace App;

use Illuminate\Support\Facades\Storage;

function writeLocalAvatar(): void
{
    Storage::disk('local')->put('avatars/user.jpg', 'contents');
}
