<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Support\Composer;

use MuhammadSadeeq\LaravelUpgradesRector\Support\Composer\ComposerJsonPathResolver;
use PHPUnit\Framework\TestCase;

final class ComposerJsonPathResolverTest extends TestCase
{
    public function testItFindsComposerJsonByWalkingUpFromAProjectFile(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/laravel-upgrades-rector-' . uniqid('', true);
        $appDirectory = $temporaryDirectory . '/app/Models';

        mkdir($appDirectory, 0777, true);
        file_put_contents($temporaryDirectory . '/composer.json', '{}');
        file_put_contents($appDirectory . '/User.php', '<?php');

        $composerJsonPathResolver = new ComposerJsonPathResolver();

        self::assertSame(
            $temporaryDirectory . '/composer.json',
            $composerJsonPathResolver->resolveFromFilePath($appDirectory . '/User.php'),
        );

        unlink($appDirectory . '/User.php');
        unlink($temporaryDirectory . '/composer.json');
        rmdir($appDirectory);
        rmdir($temporaryDirectory . '/app');
        rmdir($temporaryDirectory);
    }

    public function testItReturnsNullWhenComposerJsonCannotBeFound(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/laravel-upgrades-rector-' . uniqid('', true);
        $appDirectory = $temporaryDirectory . '/app';

        mkdir($appDirectory, 0777, true);
        file_put_contents($appDirectory . '/User.php', '<?php');

        $composerJsonPathResolver = new ComposerJsonPathResolver();

        self::assertNull($composerJsonPathResolver->resolveFromFilePath($appDirectory . '/User.php'));

        unlink($appDirectory . '/User.php');
        rmdir($appDirectory);
        rmdir($temporaryDirectory);
    }
}
