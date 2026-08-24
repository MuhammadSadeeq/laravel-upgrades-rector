<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

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
