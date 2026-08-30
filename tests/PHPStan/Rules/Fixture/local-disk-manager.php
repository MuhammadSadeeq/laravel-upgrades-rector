<?php

namespace App;

use Illuminate\Filesystem\FilesystemManager;

function writeThroughFilesystemManager(FilesystemManager $manager): void
{
    $manager->disk('local')->put('avatars/user.jpg', 'contents');
}
