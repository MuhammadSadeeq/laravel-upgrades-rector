<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

$middleware = ValidateCsrfToken::class;

?>
-----
<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

$middleware = Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class;

?>
