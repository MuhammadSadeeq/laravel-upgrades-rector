<?php

namespace App;

use Illuminate\Support\Facades\Storage;

function writeConfiguredLocalAvatar(): void
{
    Storage::disk('local')->put('avatars/user.jpg', 'contents');
    Storage::put('avatars/default.jpg', 'contents');
    Storage::disk()->put('avatars/default-chain.jpg', 'contents');
}
