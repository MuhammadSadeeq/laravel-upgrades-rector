<?php

namespace App\Fixtures\Laravel11\UpdateSanctumConfigRector;

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),
    'expiration' => null,
    'token_prefix' => '',
];

?>
