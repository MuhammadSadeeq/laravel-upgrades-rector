<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Structure;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonRepository;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonStep;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Structure\ModernStructureMigrator;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ModernStructureMigratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/laravel-modern-'.bin2hex(random_bytes(6));
        $this->copyDirectory((new SkeletonRepository)->path(10), $this->directory);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_migrates_the_laravel10_skeleton_into_a_valid_laravel11_shape(): void
    {
        $collector = new FindingCollector;
        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertContains('bootstrap/app.php', $result['changed']);
        self::assertContains('app/Http/Kernel.php', $result['deleted']);
        self::assertContains('app/Console/Kernel.php', $result['deleted']);
        self::assertContains('app/Exceptions/Handler.php', $result['deleted']);
        self::assertContains('app/Providers/RouteServiceProvider.php', $result['deleted']);
        self::assertContains('tests/CreatesApplication.php', $result['deleted']);
        self::assertFileDoesNotExist($this->directory.'/app/Http/Kernel.php');
        self::assertFileDoesNotExist($this->directory.'/app/Console/Kernel.php');
        self::assertFileDoesNotExist($this->directory.'/app/Exceptions/Handler.php');
        self::assertFileDoesNotExist($this->directory.'/app/Providers/RouteServiceProvider.php');
        self::assertFileDoesNotExist($this->directory.'/tests/CreatesApplication.php');

        $bootstrap = file_get_contents($this->directory.'/bootstrap/app.php');
        $provider = file_get_contents($this->directory.'/app/Providers/AppServiceProvider.php');
        $providers = file_get_contents($this->directory.'/bootstrap/providers.php');
        $testCase = file_get_contents($this->directory.'/tests/TestCase.php');
        self::assertIsString($bootstrap);
        self::assertIsString($provider);
        self::assertIsString($providers);
        self::assertIsString($testCase);
        self::assertStringContainsString('$middleware->use(', $bootstrap);
        self::assertStringContainsString('$exceptions->dontFlash(', $bootstrap);
        self::assertStringContainsString("api: __DIR__.'/../routes/api.php'", $bootstrap);
        self::assertStringContainsString('RateLimiter::for', $provider);
        self::assertStringContainsString('App\\Providers\\AppServiceProvider::class', $providers);
        self::assertStringNotContainsString('CreatesApplication', $testCase);

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        self::assertNotNull($parser->parse($bootstrap));
        self::assertNotNull($parser->parse($provider));
    }

    public function test_custom_kernel_is_retained_with_a_high_finding_and_plan_is_byte_neutral(): void
    {
        $kernelPath = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($kernelPath);
        self::assertIsString($kernel);
        file_put_contents($kernelPath, $kernel."\n    public function customMiddleware(): void {}\n");
        $before = $this->fileContents($this->directory);
        $collector = new FindingCollector;

        $preview = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector, true);

        self::assertSame([], $preview['changed']);
        self::assertSame([], $preview['deleted']);
        self::assertNotEmpty($preview['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertNotEmpty($result['conflicts']);
        self::assertFileExists($kernelPath);
        self::assertContains('app/Http/Kernel.php', array_map(
            static fn ($finding): string => $finding->file,
            $collector->bySeverity('high'),
        ));
    }

    public function test_slim_config_removes_only_unmodified_files_and_modern_mode_is_transition_scoped(): void
    {
        $result = (new ModernStructureMigrator)->migrate(
            $this->directory,
            10,
            11,
            new FindingCollector,
            false,
            true,
        );

        self::assertContains('config/auth.php', $result['deleted']);
        self::assertFileDoesNotExist($this->directory.'/config/auth.php');
        self::assertSame([], (new ModernStructureMigrator)->migrate($this->directory, 11, 12)['changed']);
    }

    public function test_custom_legacy_bootstrap_aborts_before_deleting_framework_files(): void
    {
        $path = $this->directory.'/bootstrap/app.php';
        $bootstrap = file_get_contents($path);
        self::assertIsString($bootstrap);
        file_put_contents($path, $bootstrap."\n// project legacy bootstrap customization\n");
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertSame(['bootstrap/app.php'], $result['conflicts']);
        self::assertFileExists($this->directory.'/app/Http/Kernel.php');
        self::assertFileExists($this->directory.'/app/Http/Middleware/Authenticate.php');
        self::assertContains('bootstrap/app.php', array_map(
            static fn ($finding): string => $finding->file,
            $collector->bySeverity('high'),
        ));
    }

    public function test_existing_modern_bootstrap_preserves_unrelated_chain_content(): void
    {
        $target = file_get_contents((new SkeletonRepository)->path(11).'/bootstrap/app.php');
        self::assertIsString($target);
        $target = str_replace("        //\n    })", "        // Existing project callback content\n    })", $target);
        $target .= "\n// Existing project bootstrap call\n";
        file_put_contents($this->directory.'/bootstrap/app.php', $target);

        (new ModernStructureMigrator)->migrate($this->directory, 10, 11);
        $result = file_get_contents($this->directory.'/bootstrap/app.php');

        self::assertIsString($result);
        self::assertStringContainsString('Existing project callback content', $result);
        self::assertStringContainsString('Existing project bootstrap call', $result);
        self::assertFileDoesNotExist($this->directory.'/app/Http/Kernel.php');
    }

    public function test_custom_http_kernel_is_not_retired_and_defaults_are_not_deleted(): void
    {
        $path = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        file_put_contents($path, $kernel."\n    public function customMiddleware(): void {}\n");
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Http/Kernel.php', $result['conflicts']);
        self::assertFileExists($path);
        self::assertFileExists($this->directory.'/app/Http/Middleware/Authenticate.php');
        self::assertContains('app/Http/Kernel.php', array_map(
            static fn ($finding): string => $finding->file,
            $collector->bySeverity('high'),
        ));
    }

    public function test_console_kernel_with_additional_command_registration_is_retained(): void
    {
        $path = $this->directory.'/app/Console/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            "        require base_path('routes/console.php');",
            "        require base_path('routes/console.php');\n        ".'$this->registerCustomCommand();',
            $kernel,
        );
        file_put_contents($path, $kernel);
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertContains('app/Console/Kernel.php', $result['conflicts']);
        self::assertFileExists($path);
        self::assertContains('app/Console/Kernel.php', array_map(
            static fn ($finding): string => $finding->file,
            $collector->bySeverity('high'),
        ));
    }

    public function test_imported_schedule_command_class_is_resolved_when_the_schedule_is_moved(): void
    {
        $path = $this->directory.'/app/Console/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            'use Illuminate\\Console\\Scheduling\\Schedule;',
            "use Illuminate\\Console\\Scheduling\\Schedule;\nuse App\\Console\\Commands\\CustomCommand;",
            $kernel,
        );
        $kernel = str_replace(
            "        // \$schedule->command('inspire')->hourly();",
            '        $schedule->command(CustomCommand::class)->daily();',
            $kernel,
        );
        self::assertIsInt(file_put_contents($path, $kernel));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);

        self::assertSame([], $result['conflicts']);
        $console = file_get_contents($this->directory.'/routes/console.php');
        self::assertIsString($console);
        self::assertStringContainsString('Schedule::command(\\App\\Console\\Commands\\CustomCommand::class)->daily()', $console);
        self::assertStringNotContainsString('CustomCommand::class)->daily()', str_replace('\\App\\Console\\Commands\\CustomCommand::class', '', $console));
    }

    public function test_schedule_external_variable_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Console/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            "        // \$schedule->command('inspire')->hourly();",
            "        \$schedule->command('inspire')->daily(\$period);",
            $kernel,
        );
        self::assertIsInt(file_put_contents($path, $kernel));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Console/Kernel.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_schedule_with_control_flow_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Console/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            "        // \$schedule->command('inspire')->hourly();",
            "        if (app()->environment('production')) {\n            ".'$schedule->command('."'inspire')->hourly();\n        }",
            $kernel,
        );
        file_put_contents($path, $kernel);
        $before = $this->fileContents($this->directory);
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertNotEmpty($result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_dynamic_route_prefix_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Providers/RouteServiceProvider.php';
        $provider = file_get_contents($path);
        self::assertIsString($provider);
        $provider = str_replace("->prefix('api')", "->prefix(config('app.api_prefix'))", $provider);
        file_put_contents($path, $provider);
        $before = $this->fileContents($this->directory);
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Providers/RouteServiceProvider.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_custom_route_file_is_rejected_before_the_route_provider_is_retired(): void
    {
        $path = $this->directory.'/routes/admin.php';
        self::assertIsInt(file_put_contents($path, "<?php\nRoute::get('/admin', static fn () => 'admin');\n"));
        $before = $this->fileContents($this->directory);
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('routes/admin.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
        self::assertFileExists($this->directory.'/app/Providers/RouteServiceProvider.php');
    }

    public function test_route_provider_with_an_extra_constant_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Providers/RouteServiceProvider.php';
        $provider = file_get_contents($path);
        self::assertIsString($provider);
        $provider = str_replace(
            "    public const HOME = '/home';",
            "    public const HOME = '/home';\n    public const CUSTOM = '/custom';",
            $provider,
        );
        self::assertIsInt(file_put_contents($path, $provider));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Providers/RouteServiceProvider.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_route_provider_with_unexpected_inheritance_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Providers/RouteServiceProvider.php';
        $provider = file_get_contents($path);
        self::assertIsString($provider);
        $provider = str_replace(
            'class RouteServiceProvider extends ServiceProvider',
            'class RouteServiceProvider extends \\Illuminate\\Support\\ServiceProvider',
            $provider,
        );
        self::assertIsInt(file_put_contents($path, $provider));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Providers/RouteServiceProvider.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_conditional_exception_registration_is_not_rewritten_or_deleted(): void
    {
        $path = $this->directory.'/app/Exceptions/Handler.php';
        $handler = file_get_contents($path);
        self::assertIsString($handler);
        $handler = str_replace(
            "        });\n    }\n}",
            "        });\n        if (app()->environment('production')) {\n            ".'$this->reportable(function (Throwable $e) {'."\n                // conditional callback\n            });\n        }\n    }\n}",
            $handler,
        );
        file_put_contents($path, $handler);
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertContains('app/Exceptions/Handler.php', $result['conflicts']);
        self::assertFileExists($path);
        self::assertStringContainsString('$this->reportable', (string) file_get_contents($path));
    }

    public function test_exception_callback_with_a_captured_variable_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Exceptions/Handler.php';
        $handler = file_get_contents($path);
        self::assertIsString($handler);
        $handler = str_replace(
            'function (Throwable $e) {',
            'function (Throwable $e) use ($context) {',
            $handler,
        );
        self::assertIsInt(file_put_contents($path, $handler));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Exceptions/Handler.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_imported_exception_type_is_resolved_when_the_handler_callback_is_moved(): void
    {
        $path = $this->directory.'/app/Exceptions/Handler.php';
        $handler = file_get_contents($path);
        self::assertIsString($handler);
        $handler = str_replace('use Throwable;', 'use Throwable as Failure;', $handler);
        $handler = str_replace('Throwable $e', 'Failure $e', $handler);
        self::assertIsInt(file_put_contents($path, $handler));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);

        self::assertSame([], $result['conflicts']);
        $bootstrap = file_get_contents($this->directory.'/bootstrap/app.php');
        self::assertIsString($bootstrap);
        self::assertStringContainsString('function (\\Throwable $e)', $bootstrap);
        self::assertStringNotContainsString('Failure $e', $bootstrap);
    }

    public function test_unresolvable_exception_type_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Exceptions/Handler.php';
        $handler = file_get_contents($path);
        self::assertIsString($handler);
        $handler = str_replace('Throwable $e', 'UnknownException $e', $handler);
        self::assertIsInt(file_put_contents($path, $handler));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Exceptions/Handler.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_imported_provider_is_extracted_without_treating_aliases_as_providers(): void
    {
        $path = $this->directory.'/config/app.php';
        $config = file_get_contents($path);
        self::assertIsString($config);
        $config = str_replace(
            'use Illuminate\\Support\\ServiceProvider;',
            "use Illuminate\\Support\\ServiceProvider;\nuse App\\Providers\\CustomProvider;\nuse App\\Facades\\AliasFacade;",
            $config,
        );
        $config = str_replace(
            '        App\\Providers\\AppServiceProvider::class,',
            "        App\\Providers\\AppServiceProvider::class,\n        CustomProvider::class,",
            $config,
        );
        $config = str_replace(
            "        // 'Example' => App\\Facades\\Example::class,",
            "        'Alias' => AliasFacade::class,",
            $config,
        );
        file_put_contents($path, $config);
        if (! is_dir($this->directory.'/app/Providers')) {
            mkdir($this->directory.'/app/Providers', 0777, true);
        }
        file_put_contents($this->directory.'/app/Providers/CustomProvider.php', "<?php\nnamespace App\\Providers;\nclass CustomProvider {}\n");

        (new ModernStructureMigrator)->migrate($this->directory, 10, 11);
        $providers = file_get_contents($this->directory.'/bootstrap/providers.php');

        self::assertIsString($providers);
        self::assertStringContainsString('CustomProvider::class', $providers);
        self::assertStringNotContainsString('AliasFacade::class', $providers);
    }

    public function test_explicit_non_app_provider_is_preserved_in_bootstrap_providers(): void
    {
        $path = $this->directory.'/config/app.php';
        $config = file_get_contents($path);
        self::assertIsString($config);
        $config = str_replace(
            'use Illuminate\\Support\\ServiceProvider;',
            "use Illuminate\\Support\\ServiceProvider;\nuse Vendor\\PackageServiceProvider;",
            $config,
        );
        $config = str_replace(
            '        App\\Providers\\AppServiceProvider::class,',
            "        App\\Providers\\AppServiceProvider::class,\n        Vendor\\PackageServiceProvider::class,",
            $config,
        );
        self::assertIsInt(file_put_contents($path, $config));

        (new ModernStructureMigrator)->migrate($this->directory, 10, 11);
        $providers = file_get_contents($this->directory.'/bootstrap/providers.php');

        self::assertIsString($providers);
        self::assertStringContainsString('App\\Providers\\AppServiceProvider::class', $providers);
        self::assertStringContainsString('Vendor\\PackageServiceProvider::class', $providers);
        self::assertStringContainsString('App\\Providers\\AuthServiceProvider::class', $providers);
        self::assertStringContainsString('App\\Providers\\EventServiceProvider::class', $providers);
        self::assertStringNotContainsString('RouteServiceProvider::class', $providers);
    }

    public function test_dynamic_provider_expression_is_rejected_without_silently_dropping_it(): void
    {
        $path = $this->directory.'/config/app.php';
        $config = file_get_contents($path);
        self::assertIsString($config);
        $config = str_replace(
            'ServiceProvider::defaultProviders()->merge([',
            'ServiceProvider::defaultProviders()->merge(providerList())',
            $config,
        );
        self::assertIsInt(file_put_contents($path, $config));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('config/app.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_dynamic_kernel_middleware_entry_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            '\\App\\Http\\Middleware\\EncryptCookies::class,',
            "config('app.web_middleware'),",
            $kernel,
        );
        self::assertIsInt(file_put_contents($path, $kernel));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Http/Kernel.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_unqualified_kernel_middleware_class_without_an_import_is_rejected_before_any_write(): void
    {
        $path = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            '\\App\\Http\\Middleware\\EncryptCookies::class,',
            'UnimportedMiddleware::class,',
            $kernel,
        );
        self::assertIsInt(file_put_contents($path, $kernel));
        $before = $this->fileContents($this->directory);

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, new FindingCollector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('app/Http/Kernel.php', $result['conflicts']);
        self::assertSame($before, $this->fileContents($this->directory));
    }

    public function test_imported_short_kernel_middleware_class_is_resolved_for_the_new_bootstrap(): void
    {
        $path = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            'use Illuminate\\Foundation\\Http\\Kernel as HttpKernel;',
            "use Illuminate\\Foundation\\Http\\Kernel as HttpKernel;\nuse App\\Http\\Middleware\\Authenticate as AuthMiddleware;",
            $kernel,
        );
        $kernel = str_replace('\\App\\Http\\Middleware\\Authenticate::class', 'AuthMiddleware::class', $kernel);
        self::assertIsInt(file_put_contents($path, $kernel));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);

        self::assertSame([], $result['conflicts']);
        $bootstrap = file_get_contents($this->directory.'/bootstrap/app.php');
        self::assertIsString($bootstrap);
        self::assertStringContainsString('\\Illuminate\\Auth\\Middleware\\Authenticate::class', $bootstrap);
        self::assertStringNotContainsString('AuthMiddleware::class', $bootstrap);
    }

    public function test_aliased_rate_limiter_imports_are_resolved_before_the_statement_is_moved(): void
    {
        $path = $this->directory.'/app/Providers/RouteServiceProvider.php';
        $provider = file_get_contents($path);
        self::assertIsString($provider);
        $provider = str_replace([
            'use Illuminate\\Cache\\RateLimiting\\Limit;',
            'use Illuminate\\Http\\Request;',
            'use Illuminate\\Support\\Facades\\RateLimiter;',
            'RateLimiter::for',
            'Request $request',
            'Limit::perMinute',
        ], [
            'use Illuminate\\Cache\\RateLimiting\\Limit as RateLimit;',
            'use Illuminate\\Http\\Request as RateRequest;',
            'use Illuminate\\Support\\Facades\\RateLimiter as RL;',
            'RL::for',
            'RateRequest $request',
            'RateLimit::perMinute',
        ], $provider);
        self::assertIsInt(file_put_contents($path, $provider));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);

        self::assertSame([], $result['conflicts']);
        $appProvider = file_get_contents($this->directory.'/app/Providers/AppServiceProvider.php');
        self::assertIsString($appProvider);
        self::assertStringContainsString('\\Illuminate\\Support\\Facades\\RateLimiter::for', $appProvider);
        self::assertStringContainsString('\\Illuminate\\Http\\Request $request', $appProvider);
        self::assertStringContainsString('\\Illuminate\\Cache\\RateLimiting\\Limit::perMinute', $appProvider);
    }

    public function test_route_middleware_and_middleware_aliases_are_both_migrated(): void
    {
        $path = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $closing = strrpos($kernel, "\n}\n");
        self::assertIsInt($closing);
        $kernel = substr($kernel, 0, $closing).<<<'PHP'

    protected $routeMiddleware = [
        'legacy-probe' => \App\Http\Middleware\Authenticate::class,
    ];
PHP
            .substr($kernel, $closing);
        self::assertIsInt(file_put_contents($path, $kernel));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);
        self::assertSame([], $result['conflicts']);
        $bootstrap = file_get_contents($this->directory.'/bootstrap/app.php');
        self::assertIsString($bootstrap);
        self::assertStringContainsString("'legacy-probe' => \\Illuminate\\Auth\\Middleware\\Authenticate::class", $bootstrap);
        self::assertStringContainsString("'auth' => \\Illuminate\\Auth\\Middleware\\Authenticate::class", $bootstrap);
    }

    public function test_custom_creates_application_keeps_trait_and_test_case_unchanged(): void
    {
        $creates = $this->directory.'/tests/CreatesApplication.php';
        $testCase = $this->directory.'/tests/TestCase.php';
        $trait = file_get_contents($creates);
        $case = file_get_contents($testCase);
        self::assertIsString($trait);
        self::assertIsString($case);
        file_put_contents($creates, $trait."\n// custom test bootstrap\n");
        $collector = new FindingCollector;

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertContains('tests/CreatesApplication.php', $result['conflicts']);
        self::assertSame($case, file_get_contents($testCase));
        self::assertStringContainsString('custom test bootstrap', (string) file_get_contents($creates));
    }

    public function test_identical_creates_application_is_removed_when_test_case_is_already_target(): void
    {
        $target = file_get_contents((new SkeletonRepository)->path(11).'/tests/TestCase.php');
        self::assertIsString($target);
        self::assertIsInt(file_put_contents($this->directory.'/tests/TestCase.php', $target));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);

        self::assertContains('tests/CreatesApplication.php', $result['deleted']);
        self::assertFileDoesNotExist($this->directory.'/tests/CreatesApplication.php');
        self::assertSame([], $result['conflicts']);
    }

    public function test_identical_creates_application_is_removed_when_test_case_is_missing(): void
    {
        self::assertTrue(unlink($this->directory.'/tests/TestCase.php'));

        $result = (new ModernStructureMigrator)->migrate($this->directory, 10, 11);

        self::assertContains('tests/CreatesApplication.php', $result['deleted']);
        self::assertFileDoesNotExist($this->directory.'/tests/CreatesApplication.php');
        self::assertSame([], $result['conflicts']);
    }

    public function test_group_definition_preserves_custom_middleware_order(): void
    {
        $path = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($path);
        self::assertIsString($kernel);
        $kernel = str_replace(
            "            \\Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse::class,\n            \\Illuminate\\Session\\Middleware\\StartSession::class,",
            "            \\Illuminate\\Session\\Middleware\\StartSession::class,\n            \\Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse::class,",
            $kernel,
        );
        file_put_contents($path, $kernel);

        (new ModernStructureMigrator)->migrate($this->directory, 10, 11);
        $bootstrap = file_get_contents($this->directory.'/bootstrap/app.php');
        self::assertIsString($bootstrap);
        $group = strpos($bootstrap, "'web'");
        $session = strpos($bootstrap, 'StartSession::class', $group === false ? 0 : $group);
        $cookies = strpos($bootstrap, 'AddQueuedCookiesToResponse::class', $group === false ? 0 : $group);
        self::assertIsInt($group);
        self::assertIsInt($session);
        self::assertIsInt($cookies);
        self::assertLessThan($cookies, $session);
    }

    public function test_second_run_is_clean_and_does_not_report_missing_legacy_files(): void
    {
        $migrator = new ModernStructureMigrator;
        $migrator->migrate($this->directory, 10, 11);
        $collector = new FindingCollector;
        $result = $migrator->migrate($this->directory, 10, 11, $collector);

        self::assertSame([], $result['changed']);
        self::assertSame([], $result['deleted']);
        self::assertSame([], $result['conflicts']);
        self::assertSame([], $collector->all());
    }

    public function test_migrated_application_boots_and_preserves_route_middleware_behavior(): void
    {
        $vendor = dirname(__DIR__, 2).'/env/laravel-11/vendor';

        if (! is_file($vendor.'/autoload.php')) {
            self::markTestSkipped('Laravel 11 vendor tree is unavailable.');
        }

        $kernelPath = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($kernelPath);
        self::assertIsString($kernel);
        $kernel = str_replace(
            'use Illuminate\\Foundation\\Http\\Kernel as HttpKernel;',
            "use Illuminate\\Foundation\\Http\\Kernel as HttpKernel;\nuse Illuminate\\Auth\\Middleware\\Authenticate as AuthMiddleware;",
            $kernel,
        );
        $kernel = str_replace('\\App\\Http\\Middleware\\Authenticate::class', 'AuthMiddleware::class', $kernel);
        self::assertIsInt(file_put_contents($kernelPath, $kernel));

        $this->prepareLaravel11Runtime($vendor);

        // The fixture is intentionally not booted before migration: its
        // Laravel 10 bootstrap/kernel contract cannot run against the local
        // Laravel 11 vendor tree. The complete copied skeleton is the
        // controlled pre-state; only the migrated application is booted.
        $collector = new FindingCollector;
        $result = (new SkeletonStep)->syncProject(
            $this->directory,
            10,
            11,
            $collector,
            false,
            'modern',
        );

        self::assertSame([], $result['conflicts']);
        self::assertFileDoesNotExist($this->directory.'/app/Http/Kernel.php');
        self::assertStringContainsString('$middleware->alias(', (string) file_get_contents($this->directory.'/bootstrap/app.php'));

        $aboutOutput = $this->runRuntimeCommand([PHP_BINARY, 'artisan', 'about', '--json']);
        $about = json_decode($aboutOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($about);
        $environment = $about['environment'] ?? null;
        self::assertIsArray($environment, $aboutOutput);
        $version = $environment['laravel_version'] ?? null;
        self::assertIsString($version, $aboutOutput);
        self::assertStringStartsWith('11.', $version);

        $routes = json_decode($this->runRuntimeCommand([PHP_BINARY, 'artisan', 'route:list', '--json']), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($routes);
        $routeByUri = [];

        foreach ($routes as $route) {
            if (is_array($route) && is_string($route['uri'] ?? null)) {
                $routeByUri[$route['uri']] = $route;
            }
        }

        self::assertArrayHasKey('/', $routeByUri);
        self::assertArrayHasKey('api/user', $routeByUri);
        $webMiddleware = $routeByUri['/']['middleware'] ?? null;
        self::assertIsArray($webMiddleware);
        self::assertContains('web', $webMiddleware);
        $apiMiddleware = $routeByUri['api/user']['middleware'] ?? null;
        self::assertIsArray($apiMiddleware);
        self::assertContains('api', $apiMiddleware);
        self::assertContains('auth:sanctum', $apiMiddleware);
        $probe = null;

        foreach ($routes as $route) {
            if (is_array($route) && ($route['uri'] ?? null) === 'upgrade-probe') {
                $probe = $route;
                break;
            }
        }

        self::assertIsArray($probe);
        $middleware = $probe['middleware'] ?? null;
        self::assertIsArray($middleware);
        self::assertContains('probe', $middleware);

        $response = json_decode($this->runRuntimeCommand([PHP_BINARY, 'runtime-probe.php']), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($response);
        self::assertSame(200, $response['status'] ?? null);
        $body = $response['body'] ?? null;
        self::assertIsArray($body);
        self::assertSame('upgrade-probe', $body['route'] ?? null);
        self::assertSame('active', $response['probe'] ?? null);
        $global = $response['global'] ?? null;
        $groups = $response['groups'] ?? null;
        $aliases = $response['aliases'] ?? null;
        self::assertIsArray($global);
        self::assertContains('Illuminate\\Http\\Middleware\\HandleCors', $global);
        self::assertIsArray($groups);
        self::assertSame([
            'Illuminate\\Cookie\\Middleware\\EncryptCookies',
            'Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse',
            'Illuminate\\Session\\Middleware\\StartSession',
            'Illuminate\\View\\Middleware\\ShareErrorsFromSession',
            'Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken',
            'Illuminate\\Routing\\Middleware\\SubstituteBindings',
        ], $groups['web'] ?? null);
        self::assertSame([
            'Illuminate\\Routing\\Middleware\\ThrottleRequests:api',
            'Illuminate\\Routing\\Middleware\\SubstituteBindings',
        ], $groups['api'] ?? null);
        self::assertIsArray($aliases);
        self::assertSame('App\\Http\\Middleware\\ProbeMiddleware', $aliases['probe'] ?? null);

        if (is_file($vendor.'/bin/phpunit')) {
            $this->runRuntimeCommand([PHP_BINARY, 'artisan', 'test', '--compact']);
        }

        $afterFirstRun = $this->fileContents($this->directory);
        $second = (new SkeletonStep)->syncProject(
            $this->directory,
            10,
            11,
            new FindingCollector,
            false,
            'modern',
        );

        $afterSecondRun = $this->fileContents($this->directory);

        self::assertSame([], $second['changed']);
        self::assertSame([], $second['conflicts']);
        self::assertSame($afterFirstRun, $afterSecondRun);
    }

    /**
     * Build a runnable Laravel 10-shaped project without installing anything.
     * The checked-in Laravel 11 environment vendor tree supplies the framework;
     * the temporary autoload wrapper adds this fixture's App namespace.
     */
    private function prepareLaravel11Runtime(string $vendor): void
    {
        $env = file_get_contents($this->directory.'/.env.example');
        self::assertIsString($env);
        $env = preg_replace('/^APP_ENV=.*$/m', 'APP_ENV=testing', $env) ?? $env;
        $env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)), $env) ?? $env;
        $env = preg_replace('/^DB_CONNECTION=.*$/m', 'DB_CONNECTION=sqlite', $env) ?? $env;
        $env = preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE='.$this->directory.'/database/database.sqlite', $env) ?? $env;
        $env = preg_replace('/^CACHE_DRIVER=.*$/m', 'CACHE_STORE=array', $env) ?? $env;
        $env = preg_replace('/^SESSION_DRIVER=.*$/m', 'SESSION_DRIVER=array', $env) ?? $env;
        self::assertIsInt(file_put_contents($this->directory.'/.env', $env));
        self::assertTrue(touch($this->directory.'/database/database.sqlite'));

        $autoload = "<?php\n\n"
            .'$loader = require '.var_export($vendor.'/autoload.php', true).";\n"
            ."\$loader->addPsr4('App\\\\', __DIR__.'/../app');\n"
            ."\$loader->addPsr4('Tests\\\\', __DIR__.'/../tests');\n"
            .'return $loader;'."\n";
        self::assertTrue(mkdir($this->directory.'/vendor', 0777, true));
        self::assertIsInt(file_put_contents($this->directory.'/vendor/autoload.php', $autoload));

        $kernelPath = $this->directory.'/app/Http/Kernel.php';
        $kernel = file_get_contents($kernelPath);
        self::assertIsString($kernel);
        $kernel = str_replace([
            '\\App\\Http\\Middleware\\Authenticate::class',
            '\\App\\Http\\Middleware\\EncryptCookies::class',
            '\\App\\Http\\Middleware\\PreventRequestsDuringMaintenance::class',
            '\\App\\Http\\Middleware\\RedirectIfAuthenticated::class',
            '\\App\\Http\\Middleware\\TrimStrings::class',
            '\\App\\Http\\Middleware\\TrustHosts::class',
            '\\App\\Http\\Middleware\\TrustProxies::class',
            '\\App\\Http\\Middleware\\ValidateSignature::class',
            '\\App\\Http\\Middleware\\VerifyCsrfToken::class',
        ], [
            '\\Illuminate\\Auth\\Middleware\\Authenticate::class',
            '\\Illuminate\\Cookie\\Middleware\\EncryptCookies::class',
            '\\Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance::class',
            '\\Illuminate\\Auth\\Middleware\\RedirectIfAuthenticated::class',
            '\\Illuminate\\Foundation\\Http\\Middleware\\TrimStrings::class',
            '\\Illuminate\\Http\\Middleware\\TrustHosts::class',
            '\\Illuminate\\Http\\Middleware\\TrustProxies::class',
            '\\Illuminate\\Routing\\Middleware\\ValidateSignature::class',
            '\\Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken::class',
        ], $kernel);
        $kernel = str_replace(
            'protected $middlewareAliases = [',
            "protected \$middlewareAliases = [\n        'probe' => \\App\\Http\\Middleware\\ProbeMiddleware::class,",
            $kernel,
        );
        self::assertIsInt(file_put_contents($kernelPath, $kernel));

        if (! is_dir($this->directory.'/app/Http/Middleware')) {
            self::assertTrue(mkdir($this->directory.'/app/Http/Middleware', 0777, true));
        }
        self::assertIsInt(file_put_contents(
            $this->directory.'/app/Http/Middleware/ProbeMiddleware.php',
            <<<'PHP'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ProbeMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $response->headers->set('X-Upgrade-Probe', 'active');

        return $response;
    }
}
PHP
        ));

        $webPath = $this->directory.'/routes/web.php';
        $web = file_get_contents($webPath);
        self::assertIsString($web);
        $web .= "\nRoute::get('/upgrade-probe', static function () {\n    return response()->json(['route' => 'upgrade-probe']);\n})->middleware('probe');\n";
        self::assertIsInt(file_put_contents($webPath, $web));

        self::assertIsInt(file_put_contents(
            $this->directory.'/runtime-probe.php',
            <<<'PHP'
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$request = Request::create('/upgrade-probe', 'GET');
$response = $kernel->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode((string) $response->getContent(), true),
    'probe' => $response->headers->get('X-Upgrade-Probe'),
    'global' => $kernel->getGlobalMiddleware(),
    'groups' => $kernel->getMiddlewareGroups(),
    'aliases' => $kernel->getMiddlewareAliases(),
], JSON_THROW_ON_ERROR);

$kernel->terminate($request, $response);
PHP
        ));
    }

    /** @param list<string> $command */
    private function runRuntimeCommand(array $command): string
    {
        $process = new Process($command, $this->directory);
        $process->setTimeout(60);
        $process->run();

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput().$process->getOutput(),
        );

        return trim($process->getOutput());
    }

    /** @return array<string, string> */
    private function fileContents(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $files[$relative] = $contents;
        }

        ksort($files);

        return $files;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        mkdir($destination, 0777, true);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            $target = $destination.'/'.$relative;

            if ($file->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0777, true);
                }

                continue;
            }

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            self::assertTrue(copy($file->getPathname(), $target));
            chmod($target, fileperms($file->getPathname()) & 0777);
        }
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

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
