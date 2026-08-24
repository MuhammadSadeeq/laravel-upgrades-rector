<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Rector;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Rector\RectorConfigGenerator;
use PHPUnit\Framework\TestCase;

final class RectorConfigGeneratorTest extends TestCase
{
    private string $projectDirectory;

    protected function setUp(): void
    {
        $this->projectDirectory = sys_get_temp_dir().'/rector-gen-'.uniqid('', true);
        mkdir($this->projectDirectory.'/app', 0777, true);
        mkdir($this->projectDirectory.'/routes', 0777, true);
    }

    protected function tearDown(): void
    {
        $config = $this->projectDirectory.'/.laravel-upgrade';
        if (is_dir($config)) {
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($config, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            ) as $fileInfo) {
                /** @var \SplFileInfo $fileInfo */
                $fileInfo->isDir() ? @rmdir($fileInfo->getPathname()) : @unlink($fileInfo->getPathname());
            }
            @rmdir($config);
        }
        @rmdir($this->projectDirectory.'/app');
        @rmdir($this->projectDirectory.'/routes');
        @rmdir($this->projectDirectory);
    }

    public function test_generates_parseable_config_with_project_paths(): void
    {
        $generator = new RectorConfigGenerator;
        $path = $generator->generate($this->projectDirectory, 13);

        self::assertFileExists($path);

        $contents = file_get_contents($path);

        if ($contents === false) {
            self::fail('generated config unreadable');
        }

        // Parseable PHP.
        exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        self::assertSame(0, $exitCode, implode("\n", $output));

        // Project paths are absolute and only existing dirs included.
        self::assertStringContainsString($this->projectDirectory.'/app', $contents);
        self::assertStringContainsString($this->projectDirectory.'/routes', $contents);
        self::assertStringNotContainsString('/bootstrap,', $contents);

        // Target set + contract rule + PHP version.
        self::assertStringContainsString('LARAVEL_13', $contents);
        self::assertStringContainsString('ImplementMissingInterfaceMethodsRector', $contents);
        self::assertStringContainsString('ContractSpecLoader::forMajor(13)', $contents);
        self::assertStringContainsString('PhpVersion::PHP_83', $contents);

        // Cache is project-local.
        self::assertStringContainsString('.laravel-upgrade/rector-cache', str_replace('\\', '/', $contents));
    }

    public function test_generated_config_for_major11_uses_php82(): void
    {
        $generator = new RectorConfigGenerator;
        $path = $generator->generate($this->projectDirectory, 11);

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString('PhpVersion::PHP_82', $contents);
        self::assertStringContainsString('ContractSpecLoader::forMajor(11)', $contents);
        self::assertDoesNotMatchRegularExpression('/LARAVEL_1[23],/', $contents);
    }
}
