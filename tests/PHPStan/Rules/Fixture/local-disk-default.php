<?php

namespace App;

use Illuminate\Support\Facades\Storage;

function useDefaultAndNamedDisks(): void
{
    Storage::put('avatars/user.jpg', 'contents');
    Storage::disk()->put('avatars/default.jpg', 'contents');
    Storage::disk('s3')->put('backups/user.jpg', 'contents');
}
