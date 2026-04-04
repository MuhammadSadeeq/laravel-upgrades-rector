<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Support\Composer;

final class ComposerJsonPathResolver
{
    public function resolveFromFilePath(string $filePath): ?string
    {
        $directory = is_dir($filePath) ? $filePath : dirname($filePath);

        while ($directory !== '' && $directory !== DIRECTORY_SEPARATOR && is_dir($directory)) {
            $composerJsonPath = $directory . DIRECTORY_SEPARATOR . 'composer.json';

            if (is_file($composerJsonPath)) {
                return $composerJsonPath;
            }

            $parentDirectory = dirname($directory);

            if ($parentDirectory === $directory) {
                break;
            }

            $directory = $parentDirectory;
        }

        return null;
    }
}
