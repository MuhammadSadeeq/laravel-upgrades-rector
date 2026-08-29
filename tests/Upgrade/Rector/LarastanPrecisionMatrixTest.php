<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Rector;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\PhpStanConfigGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Rector\RectorConfigGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Exercises generated Rector and PHPStan configurations through their real
 * binaries.  The two projects in each matrix use the same Laravel vendor
 * tree; only the Larastan extension tree is present in the precision case.
 */
final class LarastanPrecisionMatrixTest extends TestCase
{
    private string $repoRoot;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 3);
        $missing = [];

        foreach (['11', '12'] as $env) {
            $envVendor = $this->envVendor($env);

            if (! is_file($envVendor.'/autoload.php') || ! is_file($envVendor.'/larastan/larastan/extension.neon')) {
                $missing[] = 'laravel-'.$env;
            }
        }

        if ($missing !== []) {
            self::markTestSkipped('The local Larastan matrix vendor trees are unavailable: '.implode(', ', $missing));
        }
    }

    public function test_generated_rector_config_has_precision_and_is_idempotent(): void
    {
        foreach (['11', '12'] as $env) {
            $withLarastan = $this->runRectorProject($env, true);
            $withoutLarastan = $this->runRectorProject($env, false);

            self::assertStringContainsString('withPHPStanConfigs', $withLarastan['config']);
            self::assertStringNotContainsString('withPHPStanConfigs', $withoutLarastan['config']);

            // Ordinary PHPStan type information is sufficient for direct Carbon
            // receivers in both projects. Cashier and Request changes belong
            // to the Laravel 11 target set only.
            foreach ([$withLarastan['source'], $withoutLarastan['source']] as $source) {
                self::assertStringContainsString('(int) abs($date->diffInDays(Carbon::now()))', $source);
                self::assertStringContainsString('$unresolved->name', $source);
            }

            if ($env === '11') {
                self::assertStringContainsString('$subscription->type', $withLarastan['source']);
                self::assertStringContainsString('$subscription->type', $withoutLarastan['source']);
                self::assertStringContainsString('$exception->redirectTo($request)', $withLarastan['source']);
                self::assertStringContainsString('$exception->redirectTo($request)', $withoutLarastan['source']);
            } else {
                self::assertStringContainsString('$subscription->name', $withLarastan['source']);
                self::assertStringContainsString('$subscription->name', $withoutLarastan['source']);
                self::assertStringContainsString('$exception->redirectTo()', $withLarastan['source']);
                self::assertStringContainsString('$exception->redirectTo()', $withoutLarastan['source']);
            }

            // The Eloquent accessor is only discovered by Larastan. An unknown
            // receiver stays unchanged in either case; no variable-name guessing.
            self::assertStringContainsString('(int) abs($post->created_at->diffInDays(Carbon::now()))', $withLarastan['source']);
            self::assertStringContainsString('$post->created_at->diffInDays(Carbon::now())', $withoutLarastan['source']);

            self::assertSame($withLarastan['source'], $withLarastan['secondSource']);
            self::assertSame($withoutLarastan['source'], $withoutLarastan['secondSource']);
            self::assertSame(0, $withLarastan['secondTotals']['changed_files']);
            self::assertSame(0, $withoutLarastan['secondTotals']['changed_files']);
        }
    }

    public function test_generated_phpstan_config_loads_optional_larastan_and_reports_unresolved_diff(): void
    {
        foreach (['11', '12'] as $env) {
            $withLarastan = $this->runPhpStanProject(true, $env);
            $withoutLarastan = $this->runPhpStanProject(false, $env);

            self::assertStringContainsString('/larastan/larastan/extension.neon', $withLarastan['config']);
            self::assertStringNotContainsString('/larastan/larastan/extension.neon', $withoutLarastan['config']);

            if ($env === '11') {
                self::assertSame([], $withLarastan['carbonFindings']);
                self::assertNotEmpty($withoutLarastan['carbonFindings']);
            } else {
                self::assertSame([], $withLarastan['carbonFindings']);
                self::assertSame([], $withoutLarastan['carbonFindings']);
            }
        }
    }

    /**
     * @return array{config: string, source: string, secondSource: string, secondTotals: array{changed_files: int}}
     */
    private function runRectorProject(string $env, bool $withLarastan): array
    {
        $envVendor = $this->envVendor($env);
        $project = sys_get_temp_dir().'/larastan-matrix-'.($withLarastan ? 'with-' : 'without-').uniqid('', true);
        $this->createProjectDirectories($project);

        try {
            $this->writeProjectAutoload($project, $env, $withLarastan);
            copy($envVendor.'/composer/installed.json', $project.'/vendor/composer/installed.json');

            if ($withLarastan) {
                mkdir($project.'/vendor/larastan', 0777, true);
                symlink($envVendor.'/larastan/larastan', $project.'/vendor/larastan/larastan');
            }

            $this->writeBootstrap($project);
            $this->writeModel($project);
            file_put_contents($project.'/app/Probe.php', <<<'PHP'
<?php

namespace App;

use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Cashier\Subscription;

function probe(Carbon $date, Subscription $subscription, Post $post, Request $request, mixed $unresolved): void
{
    $direct = $date->diffInDays(Carbon::now());
    $model = $post->created_at->diffInDays(Carbon::now());
    $cashier = $subscription->name;
    $unknownCashier = $unresolved->name;
    $exception = new AuthenticationException();
    $redirect = $exception->redirectTo();
}
PHP);

            $config = (new RectorConfigGenerator)->generate($project, (int) $env);
            $this->runRector($project, $config);
            self::assertSame($withLarastan ? 'present' : 'absent', file_get_contents($project.'/larastan-sentinel'));
            $source = (string) file_get_contents($project.'/app/Probe.php');
            $secondOutput = $this->runRector($project, $config);
            $secondSource = (string) file_get_contents($project.'/app/Probe.php');
            $secondJson = $this->decodeJsonOutput($secondOutput);

            if (! is_array($secondJson) || ! is_array($secondJson['totals'] ?? null)) {
                self::fail('Rector returned malformed JSON totals.');
            }

            $changedFiles = $secondJson['totals']['changed_files'] ?? null;

            if (! is_int($changedFiles)) {
                self::fail('Rector returned a non-integer changed_files total.');
            }

            return [
                'config' => (string) file_get_contents($config),
                'source' => $source,
                'secondSource' => $secondSource,
                'secondTotals' => ['changed_files' => $changedFiles],
            ];
        } finally {
            $this->removeDirectory($project);
        }
    }

    /**
     * @return array{config: string, carbonFindings: list<string>}
     */
    private function runPhpStanProject(bool $withLarastan, string $env): array
    {
        $envVendor = $this->envVendor($env);
        $project = sys_get_temp_dir().'/phpstan-matrix-'.($withLarastan ? 'with-' : 'without-').uniqid('', true);
        $this->createProjectDirectories($project);

        try {
            $this->writeProjectAutoload($project, $env, $withLarastan);
            copy($envVendor.'/composer/installed.json', $project.'/vendor/composer/installed.json');

            if ($withLarastan) {
                mkdir($project.'/vendor/larastan', 0777, true);
                symlink($envVendor.'/larastan/larastan', $project.'/vendor/larastan/larastan');
            }

            $this->writeBootstrap($project);
            $this->writeModel($project);
            file_put_contents($project.'/app/Probe.php', <<<'PHP'
<?php

namespace App;

use App\Models\Post;
use Carbon\Carbon;

function probe(Post $post): void
{
    $model = $post->created_at->diffInDays(Carbon::now());
}
PHP);

            $configPath = (new PhpStanConfigGenerator($this->repoRoot))->generate(
                $project,
                (int) $env,
                $project.'/.laravel-upgrade',
            );
            $process = new Process([
                $envVendor.'/bin/phpstan',
                'analyse',
                '-c',
                $configPath,
                '--error-format=json',
                '--no-progress',
                '--memory-limit=-1',
                $project.'/app',
            ], $project, null, null, 180.0);
            $process->run();

            self::assertSame($withLarastan ? 'present' : 'absent', file_get_contents($project.'/larastan-sentinel'));

            if (! in_array($process->getExitCode(), [0, 1], true)) {
                self::fail($process->getErrorOutput().$process->getOutput());
            }

            $json = $this->decodeJsonOutput($process->getOutput());
            if (! is_array($json)) {
                self::fail($process->getErrorOutput().$process->getOutput());
            }

            if (! is_array($json['totals'] ?? null)) {
                self::fail('PHPStan returned no totals object.');
            }

            if (! is_array($json['errors'] ?? null) || $json['errors'] !== []) {
                self::fail('PHPStan returned top-level errors: '.json_encode($json['errors'] ?? null));
            }

            if (array_key_exists('internalErrors', $json) && $json['internalErrors'] !== []) {
                self::fail('PHPStan returned internal errors: '.json_encode($json['internalErrors']));
            }

            if (! is_array($json['files'] ?? null)) {
                self::fail('PHPStan returned no files object.');
            }

            $carbonFindings = [];

            $files = $json['files'];

            foreach ($files as $file) {
                if (! is_array($file) || ! is_array($file['messages'] ?? null)) {
                    continue;
                }

                foreach ($file['messages'] as $message) {
                    if (is_array($message) && ($message['identifier'] ?? null) === 'laravelUpgrade.carbonUntypedDiff') {
                        $carbonFindings[] = 'laravelUpgrade.carbonUntypedDiff';
                    }
                }
            }

            return [
                'config' => (string) file_get_contents($configPath),
                'carbonFindings' => $carbonFindings,
            ];
        } finally {
            $this->removeDirectory($project);
        }
    }

    private function createProjectDirectories(string $project): void
    {
        mkdir($project.'/app/Models', 0777, true);
        mkdir($project.'/app/Support', 0777, true);
        mkdir($project.'/bootstrap/cache', 0777, true);
        mkdir($project.'/routes', 0777, true);
        mkdir($project.'/vendor/composer', 0777, true);
    }

    private function writeBootstrap(string $project): void
    {
        file_put_contents($project.'/bootstrap/app.php', <<<'PHP'
<?php

use Illuminate\Foundation\Application;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php')
    ->create();
PHP);
        file_put_contents($project.'/routes/web.php', "<?php\n");
    }

    private function writeModel(string $project): void
    {
        file_put_contents($project.'/app/Models/Post.php', <<<'PHP'
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

final class Post extends Model
{
    /** @return Attribute<Carbon, string> */
    protected function createdAt(): Attribute
    {
        return Attribute::make(get: static fn (): Carbon => Carbon::now());
    }
}
PHP);
    }

    private function writeProjectAutoload(string $project, string $env, bool $withLarastan): void
    {
        $envVendor = $this->envVendor($env);
        $laravelVersion = $env === '11' ? '11.56.0' : '12.67.0';
        if ($withLarastan) {
            $autoload = "<?php\nrequire_once ".var_export($envVendor.'/autoload.php', true).";\n";
        } else {
            $autoload = "<?php\n\$envLoader = require ".var_export($envVendor.'/autoload.php', true).";\n"
                ."\$envLoader->unregister();\n"
                ."spl_autoload_register(static function (string \$class) use (\$envLoader): void {\n"
                ."    if (str_starts_with(\$class, 'Larastan\\\\')) {\n"
                ."        return;\n"
                ."    }\n"
                ."    \$envLoader->loadClass(\$class);\n"
                ."});\n";
        }

        $autoload .= 'require_once '.var_export($this->repoRoot.'/vendor/autoload.php', true).";\n";

        if ($withLarastan) {
            $autoload .= "if (!defined('Larastan\\\\Larastan\\\\LARAVEL_VERSION')) { define('Larastan\\\\Larastan\\\\LARAVEL_VERSION', ".var_export($laravelVersion, true)."); }\n";
        }

        $autoload .= '$app = require '.var_export($project.'/bootstrap/app.php', true).";\n";
        $autoload .= "\\Illuminate\\Container\\Container::setInstance(\$app);\n";
        $autoload .= "spl_autoload_register(static function (string \$class): void {\n"
            ."    if (\$class === 'Laravel\\\\Cashier\\\\Subscription') {\n"
            .'        require '.var_export($project.'/app/Support/Subscription.php', true).";\n"
            ."    }\n});\n";

        // This runs in the real Rector/PHPStan child process, after the
        // project loader is installed. It proves the no-Larastan fixture does
        // not accidentally expose the extension through its Composer loader.
        $autoload .= 'file_put_contents('.var_export($project.'/larastan-sentinel', true).", class_exists('Larastan\\\\Larastan\\\\ApplicationResolver') ? 'present' : 'absent');\n";

        file_put_contents($project.'/vendor/autoload.php', $autoload);
        file_put_contents($project.'/app/Support/Subscription.php', <<<'PHP'
<?php

namespace Laravel\Cashier;

final class Subscription
{
    public string $name;
    public string $type;
}
PHP);
    }

    private function envVendor(string $env): string
    {
        return $this->repoRoot.'/tests/env/laravel-'.$env.'/vendor';
    }

    private function runRector(string $project, string $config): string
    {
        $process = new Process([
            $this->repoRoot.'/vendor/bin/rector',
            'process',
            '--config='.$config,
            '--output-format=json',
            '--no-progress-bar',
            '--clear-cache',
            '--autoload-file',
            $project.'/vendor/autoload.php',
        ], $project, null, null, 180.0);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
        $json = $this->decodeJsonOutput($process->getOutput());
        self::assertIsArray($json, $process->getOutput());
        self::assertIsArray($json['totals'] ?? null, $process->getOutput());

        return $process->getOutput();
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonOutput(string $output): ?array
    {
        $start = strpos($output, '{');

        if ($start === false) {
            return null;
        }

        $decoded = json_decode(substr($output, $start), true);

        if (! is_array($decoded)) {
            return null;
        }

        $result = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                return null;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! is_link($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $path = $fileInfo->getPathname();

            if ($fileInfo->isLink()) {
                unlink($path);
            } elseif ($fileInfo->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
