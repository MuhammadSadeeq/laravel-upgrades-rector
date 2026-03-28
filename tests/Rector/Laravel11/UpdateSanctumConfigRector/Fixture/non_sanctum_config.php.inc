<?php

return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'middleware' => [
        'authenticate_session' => 'SomeOtherClass',
    ],
];

?>
