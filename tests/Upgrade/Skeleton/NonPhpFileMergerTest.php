<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Skeleton;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\NonPhpFileMerger;
use PHPUnit\Framework\TestCase;

final class NonPhpFileMergerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-upgrade-non-php-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/from', 0777, true);
        mkdir($this->directory.'/to', 0777, true);
        mkdir($this->directory.'/project', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_phpunit_schema_uses_the_truthfully_detected_locked_major(): void
    {
        $from = '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd" />';
        $to = '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/12.0/phpunit.xsd" />';
        file_put_contents($this->directory.'/from/phpunit.xml', $from);
        file_put_contents($this->directory.'/to/phpunit.xml', $to);
        file_put_contents($this->directory.'/project/phpunit.xml', $from);
        file_put_contents($this->directory.'/project/composer.lock', json_encode([
            'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '11.5.0']],
        ], JSON_THROW_ON_ERROR));

        $result = (new NonPhpFileMerger)->sync(
            $this->directory.'/project',
            $this->directory.'/from',
            $this->directory.'/to',
        );

        self::assertSame(['phpunit.xml'], $result['changed']);
        self::assertSame(
            '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd" />',
            file_get_contents($this->directory.'/project/phpunit.xml'),
        );
    }

    public function test_missing_phpunit_provenance_does_not_infer_target_schema(): void
    {
        $from = '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd" />';
        $to = '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/12.0/phpunit.xsd" />';
        file_put_contents($this->directory.'/from/phpunit.xml', $from);
        file_put_contents($this->directory.'/to/phpunit.xml', $to);
        file_put_contents($this->directory.'/project/phpunit.xml', $from);

        $result = (new NonPhpFileMerger)->sync(
            $this->directory.'/project',
            $this->directory.'/from',
            $this->directory.'/to',
        );

        self::assertSame([], $result['changed']);
        self::assertSame($from, file_get_contents($this->directory.'/project/phpunit.xml'));
    }

    public function test_installed_phpunit_metadata_wins_over_a_stale_lock(): void
    {
        $from = '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd" />';
        $to = '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/12.0/phpunit.xsd" />';
        file_put_contents($this->directory.'/from/phpunit.xml', $from);
        file_put_contents($this->directory.'/to/phpunit.xml', $to);
        file_put_contents($this->directory.'/project/phpunit.xml', $from);
        mkdir($this->directory.'/project/vendor/composer', 0777, true);
        file_put_contents($this->directory.'/project/vendor/composer/installed.json', json_encode([
            'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '12.0.0']],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->directory.'/project/composer.lock', json_encode([
            'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '10.5.0']],
        ], JSON_THROW_ON_ERROR));

        (new NonPhpFileMerger)->sync(
            $this->directory.'/project',
            $this->directory.'/from',
            $this->directory.'/to',
        );

        self::assertSame(
            '<phpunit xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/12.0/phpunit.xsd" />',
            file_get_contents($this->directory.'/project/phpunit.xml'),
        );
    }

    public function test_owned_file_is_copied_only_for_a_true_upstream_addition(): void
    {
        file_put_contents($this->directory.'/to/.gitignore', "target\n");
        chmod($this->directory.'/to/.gitignore', 0755);
        $result = (new NonPhpFileMerger)->sync(
            $this->directory.'/project',
            $this->directory.'/from',
            $this->directory.'/to',
        );

        self::assertContains('.gitignore', $result['changed']);
        self::assertSame("target\n", file_get_contents($this->directory.'/project/.gitignore'));
        self::assertSame(0755, fileperms($this->directory.'/project/.gitignore') & 0777);

        file_put_contents($this->directory.'/from/.editorconfig', "base\n");
        file_put_contents($this->directory.'/to/.editorconfig', "target\n");
        $result = (new NonPhpFileMerger)->sync(
            $this->directory.'/project',
            $this->directory.'/from',
            $this->directory.'/to',
        );

        self::assertFileDoesNotExist($this->directory.'/project/.editorconfig');
        self::assertNotContains('.editorconfig', $result['changed']);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
        }

        rmdir($directory);
    }
}
