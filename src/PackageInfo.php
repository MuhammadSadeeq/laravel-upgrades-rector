<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector;

/**
 * Canonical package identity used by the CLI and generated reports.
 *
 * Keep the package's release version in one place. Composer deliberately does
 * not carry a second version field for libraries, so runtime surfaces use this
 * class instead of maintaining their own copy of the release number.
 */
final class PackageInfo
{
    public const NAME = 'muhammadsadeeq/laravel-upgrades-rector';

    public const VERSION = '1.0.0';

    public const TOOL = self::NAME.'/'.self::VERSION;

    private function __construct() {}
}
