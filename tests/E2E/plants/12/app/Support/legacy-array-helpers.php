<?php

declare(strict_types=1);

// PHP 8.5/polyfill environments may already provide these names. The guard
// keeps the legacy fixture loadable while PHPStan still sees the declarations.
if (! function_exists('array_first')) {
    function array_first(array $array, ?Closure $callback = null): mixed
    {
        if ($callback === null) {
            return $array === [] ? null : reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return null;
    }
}

if (! function_exists('array_last')) {
    function array_last(array $array, ?Closure $callback = null): mixed
    {
        $array = array_reverse($array, true);

        return array_first($array, $callback);
    }
}
