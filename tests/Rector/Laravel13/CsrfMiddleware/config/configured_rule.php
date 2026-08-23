<?php

declare(strict_types=1);

use MuhammadSadeeq\LaravelUpgradesRector\Rector\Laravel13\RenameValidateCsrfTokensMethodRector;
use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

return RectorConfig::configure()
    ->withImportNames()
    ->withConfiguredRule(RenameClassRector::class, [
        'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken' => 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken' => 'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
    ])
    ->withRules([
        RenameValidateCsrfTokensMethodRector::class,
    ]);
