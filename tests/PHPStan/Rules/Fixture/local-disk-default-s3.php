<?php

namespace App;

use Illuminate\Support\Facades\Storage;

function useS3AsDefaultDisk(): void
{
    Storage::put('avatars/user.jpg', 'contents');
    Storage::disk()->put('backups/user.jpg', 'contents');
}
