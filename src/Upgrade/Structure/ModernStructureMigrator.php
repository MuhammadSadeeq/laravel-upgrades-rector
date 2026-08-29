<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Structure;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Skeleton\SkeletonRepository;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;
use Throwable;

/**
 * Converts a Laravel 10 application structure to the Laravel 11 slim
 * application bootstrap. The migrator is deliberately conservative: it
 * reports constructs it cannot prove safe, and only removes files which are
 * byte-identical to the Laravel 10 snapshot.
 *
 * @phpstan-type MigrationResult array{changed: list<string>, deleted: list<string>, conflicts: list<string>}
 */
final class ModernStructureMigrator
{
    /** @var list<string> */
    private const DEFAULT_GLOBAL = [
        '\\Illuminate\\Foundation\\Http\\Middleware\\InvokeDeferredCallbacks::class',
        '\\Illuminate\\Http\\Middleware\\TrustHosts::class',
        '\\Illuminate\\Http\\Middleware\\TrustProxies::class',
        '\\Illuminate\\Http\\Middleware\\HandleCors::class',
        '\\Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance::class',
        '\\Illuminate\\Http\\Middleware\\ValidatePostSize::class',
        '\\Illuminate\\Foundation\\Http\\Middleware\\TrimStrings::class',
        '\\Illuminate\\Foundation\\Http\\Middleware\\ConvertEmptyStringsToNull::class',
    ];

    /** @var array<string, list<string>> */
    private const DEFAULT_GROUPS = [
        'web' => [
            '\\Illuminate\\Cookie\\Middleware\\EncryptCookies::class',
            '\\Illuminate\\Cookie\\Middleware\\AddQueuedCookiesToResponse::class',
            '\\Illuminate\\Session\\Middleware\\StartSession::class',
            '\\Illuminate\\View\\Middleware\\ShareErrorsFromSession::class',
            '\\Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken::class',
            '\\Illuminate\\Routing\\Middleware\\SubstituteBindings::class',
        ],
        'api' => [
            '\\Illuminate\\Routing\\Middleware\\SubstituteBindings::class',
        ],
    ];

    /** @var array<string, string> */
    private const DEFAULT_ALIASES = [
        'auth' => '\\App\\Http\\Middleware\\Authenticate::class',
        'auth.basic' => '\\Illuminate\\Auth\\Middleware\\AuthenticateWithBasicAuth::class',
        'auth.session' => '\\Illuminate\\Auth\\Middleware\\AuthenticateSession::class',
        'cache.headers' => '\\Illuminate\\Http\\Middleware\\SetCacheHeaders::class',
        'can' => '\\Illuminate\\Auth\\Middleware\\Authorize::class',
        'guest' => '\\App\\Http\\Middleware\\RedirectIfAuthenticated::class',
        'password.confirm' => '\\Illuminate\\Auth\\Middleware\\RequirePassword::class',
        'precognitive' => '\\Illuminate\\Foundation\\Http\\Middleware\\HandlePrecognitiveRequests::class',
        'signed' => '\\Illuminate\\Routing\\Middleware\\ValidateSignature::class',
        'throttle' => '\\Illuminate\\Routing\\Middleware\\ThrottleRequests::class',
        'verified' => '\\Illuminate\\Auth\\Middleware\\EnsureEmailIsVerified::class',
    ];

    private Parser $parser;

    private Standard $printer;

    private NodeFinder $finder;

    public function __construct(private readonly SkeletonRepository $repository = new SkeletonRepository)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->printer = new Standard;
        $this->finder = new NodeFinder;
    }

    /** @return MigrationResult */
    private function emptyResult(): array
    {
        return ['changed' => [], 'deleted' => [], 'conflicts' => []];
    }

    /** @param MigrationResult $result
     * @return MigrationResult
     */
    private function normalizeResult(array $result): array
    {
        $result['changed'] = array_values(array_unique($result['changed']));
        $result['deleted'] = array_values(array_unique($result['deleted']));
        $result['conflicts'] = array_values(array_unique($result['conflicts']));

        return $result;
    }

    /** @return MigrationResult */
    public function migrate(
        string $projectDirectory,
        int $fromMajor,
        int $targetMajor,
        ?FindingCollector $collector = null,
        bool $dryRun = false,
        bool $slimConfig = false,
    ): array {
        /** @var MigrationResult $result */
        $result = $this->emptyResult();

        if ($fromMajor !== 10 || $targetMajor !== 11) {
            return $result;
        }

        $from = $this->repository->path(10);
        $to = $this->repository->path(11);

        if (! is_dir($projectDirectory) || ! is_dir($from) || ! is_dir($to)
            || ! $this->repository->isComplete(10) || ! $this->repository->isComplete(11)) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'Modern structure migration requires complete Laravel 10 and 11 snapshots.',
                'Refresh complete skeleton snapshots before using --structure=modern.',
                $targetMajor,
                'resources/skeletons/MANIFEST.json',
            );

            return $result;
        }

        $bootstrapPath = $projectDirectory.'/bootstrap/app.php';
        $baseBootstrapPath = $from.'/bootstrap/app.php';
        $targetBootstrapPath = $to.'/bootstrap/app.php';
        $kernelPath = $projectDirectory.'/app/Http/Kernel.php';
        $baseKernel = $from.'/app/Http/Kernel.php';
        $projectBootstrap = is_file($bootstrapPath) ? file_get_contents($bootstrapPath) : false;
        $baseBootstrap = is_file($baseBootstrapPath) ? file_get_contents($baseBootstrapPath) : false;
        $targetBootstrap = is_file($targetBootstrapPath) ? file_get_contents($targetBootstrapPath) : false;
        $kernel = is_file($kernelPath) ? file_get_contents($kernelPath) : false;

        if ($projectBootstrap === false || $baseBootstrap === false || $targetBootstrap === false) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'Modern structure migration could not find the project and snapshot bootstrap/app.php files.',
                'Migrate the application structure manually and rerun with --structure=keep.',
                $targetMajor,
                'bootstrap/app.php',
            );

            return $result;
        }

        $alreadyModern = $this->isModernBootstrap($projectBootstrap);

        if ($projectBootstrap !== $baseBootstrap && ! $alreadyModern) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The customized legacy bootstrap/app.php could not be safely converted to Laravel 11 structure.',
                'Migrate bootstrap/app.php manually or restore the Laravel 10 skeleton before using --structure=modern.',
                $targetMajor,
                'bootstrap/app.php',
            );
            $result['conflicts'][] = 'bootstrap/app.php';

            return $result;
        }

        if ($alreadyModern && (! str_contains($projectBootstrap, '->withMiddleware(function (Middleware $middleware) {')
            || ! str_contains($projectBootstrap, '->withExceptions(function (Exceptions $exceptions) {'))) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The existing Laravel 11 bootstrap callback form could not be safely merged.',
                'Move middleware and exception configuration manually before using --structure=modern.',
                $targetMajor,
                'bootstrap/app.php',
            );
            $result['conflicts'][] = 'bootstrap/app.php';

            return $result;
        }

        if (! $this->preflight(
            $projectDirectory,
            $from,
            $to,
            $collector,
            $result,
            $alreadyModern,
            $kernel,
        )) {
            return $this->normalizeResult($result);
        }

        // A pristine Laravel 10 bootstrap is replaced by Laravel 11's
        // chain. A modern bootstrap is treated as the user's source so that
        // unrelated calls and callback bodies remain intact.
        $bootstrap = $projectBootstrap === $baseBootstrap ? $targetBootstrap : $projectBootstrap;

        if ($kernel !== false) {
            $kernelClass = $this->class($kernel, 'app/Http/Kernel.php', $collector, $targetMajor);

            if ($kernelClass !== null) {
                $middleware = $this->middlewareCallback($kernelClass, $projectDirectory, $from, $kernel);
                $bootstrap = $this->replaceCallback($bootstrap, 'withMiddleware', 'Middleware $middleware', $middleware);
                $this->replaceFile($projectDirectory, $bootstrapPath, $bootstrap, $targetBootstrapPath, $dryRun, $result);

                $kernelDeleted = $this->retireSafeComponent(
                    $kernelPath,
                    $baseKernel,
                    'app/Http/Kernel.php',
                    $projectDirectory,
                    $collector,
                    $targetMajor,
                    $dryRun,
                    $result,
                    $this->safeKernelClass($kernelClass, $kernel),
                );

                if ($kernelDeleted) {
                    $this->removeDefaultMiddleware($projectDirectory, $from, $collector, $dryRun, $result);
                } else {
                    $result['conflicts'][] = 'app/Http/Kernel.php';
                }
            }
        } elseif (! $alreadyModern) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The Laravel 10 HTTP Kernel is missing, so middleware behavior could not be verified.',
                'Restore or migrate app/Http/Kernel.php manually before using --structure=modern.',
                $targetMajor,
                'app/Http/Kernel.php',
            );
            $result['conflicts'][] = 'app/Http/Kernel.php';
        }

        $bootstrapContents = $bootstrap;
        $this->migrateExceptions($projectDirectory, $from, $collector, $dryRun, $result, $bootstrapContents);
        $this->migrateConsole($projectDirectory, $from, $to, $collector, $dryRun, $result);
        $this->migrateRouting($projectDirectory, $from, $to, $collector, $dryRun, $result, $bootstrapPath, $bootstrapContents);
        $this->migrateProviders($projectDirectory, $from, $to, $collector, $dryRun, $result);
        $this->migrateTests($projectDirectory, $from, $to, $collector, $dryRun, $result);

        if ($slimConfig) {
            $this->slimConfig($projectDirectory, $from, $collector, $dryRun, $result, $targetMajor);
        }

        return $this->normalizeResult($result);
    }

    private function class(string $source, string $file, ?FindingCollector $collector, int $major): ?Class_
    {
        try {
            $nodes = $this->parser->parse($source) ?? [];
        } catch (Throwable $exception) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                sprintf('Could not parse %s for modern structure migration: %s', $file, $exception->getMessage()),
                'Review this file manually; no automatic structural transformation was applied.',
                $major,
                $file,
            );

            return null;
        }

        $class = $this->finder->findFirstInstanceOf($nodes, Class_::class);

        if (! $class instanceof Class_) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                sprintf('No named class was found in %s for modern structure migration.', $file),
                'Review this file manually; no automatic structural transformation was applied.',
                $major,
                $file,
            );

            return null;
        }

        return $class;
    }

    private function isModernBootstrap(string $source): bool
    {
        return str_contains($source, 'Application::configure')
            && str_contains($source, '->withRouting(')
            && str_contains($source, '->withMiddleware(')
            && str_contains($source, '->withExceptions(');
    }

    /**
     * Analyze every legacy component before any modernization write or
     * deletion. A conflict is deliberately all-or-nothing: the caller can
     * show the findings and retry after the project has been made safe.
     *
     * @param  MigrationResult  $result
     */
    private function preflight(
        string $projectDirectory,
        string $from,
        string $to,
        ?FindingCollector $collector,
        array &$result,
        bool $alreadyModern,
        string|false $kernel,
    ): bool {
        $safe = true;

        if ($kernel === false && ! $alreadyModern) {
            $this->unsafe(
                $collector,
                $result,
                'The Laravel 10 HTTP Kernel is missing, so middleware behavior could not be verified.',
                'Restore or migrate app/Http/Kernel.php manually before using --structure=modern.',
                'app/Http/Kernel.php',
            );
            $safe = false;
        } elseif ($kernel !== false) {
            $class = $this->class($kernel, 'app/Http/Kernel.php', $collector, 11);

            if ($class === null || ! $this->safeKernelClass($class, $kernel)) {
                $this->unsafe(
                    $collector,
                    $result,
                    'The HTTP Kernel contains behavior that cannot be proven safe to move to bootstrap/app.php.',
                    'Migrate the customized middleware stack manually before using --structure=modern.',
                    'app/Http/Kernel.php',
                );
                $safe = false;
            }
        }

        $handlerPath = $projectDirectory.'/app/Exceptions/Handler.php';

        if (is_file($handlerPath)) {
            $source = file_get_contents($handlerPath);
            $class = is_string($source) ? $this->class($source, 'app/Exceptions/Handler.php', $collector, 11) : null;

            if ($class === null || ! $this->safeHandlerClass($class, $source)) {
                $this->unsafe(
                    $collector,
                    $result,
                    'The exception Handler contains behavior that cannot be proven safe to move to bootstrap/app.php.',
                    'Migrate custom exception behavior manually before using --structure=modern.',
                    'app/Exceptions/Handler.php',
                );
                $safe = false;
            }
        }

        $consolePath = $projectDirectory.'/app/Console/Kernel.php';

        if (is_file($consolePath)) {
            $source = file_get_contents($consolePath);
            $class = is_string($source) ? $this->class($source, 'app/Console/Kernel.php', $collector, 11) : null;

            if ($class === null || ! $this->safeConsoleClass($class, $from, $to, $projectDirectory, $source)) {
                $this->unsafe(
                    $collector,
                    $result,
                    'The console Kernel contains behavior that cannot be proven safe to move to routes/console.php.',
                    'Migrate custom commands or scheduling manually before using --structure=modern.',
                    'app/Console/Kernel.php',
                );
                $safe = false;
            }
        }

        $routePath = $projectDirectory.'/app/Providers/RouteServiceProvider.php';

        if (is_file($routePath)) {
            $source = file_get_contents($routePath);
            $class = is_string($source) ? $this->class($source, 'app/Providers/RouteServiceProvider.php', $collector, 11) : null;

            if ($class === null || ! $this->safeRouteProviderClass($class, $source, $projectDirectory, $to)) {
                $this->unsafe(
                    $collector,
                    $result,
                    'The RouteServiceProvider contains routing behavior that cannot be proven safe to move to bootstrap/app.php.',
                    'Migrate custom routing and rate limiting manually before using --structure=modern.',
                    'app/Providers/RouteServiceProvider.php',
                );
                $safe = false;
            }

            foreach ($this->unexpectedRouteFiles($projectDirectory) as $relative) {
                $this->unsafe(
                    $collector,
                    $result,
                    sprintf('The route provider has unsupported route file "%s".', $relative),
                    'Move custom route loading into bootstrap/app.php manually before using --structure=modern.',
                    $relative,
                );
                $safe = false;
            }
        }

        if (! $this->safeTestBootstrap($projectDirectory, $from, $to)) {
            $this->unsafe(
                $collector,
                $result,
                'The customized test bootstrap cannot be safely retired during modern structure migration.',
                'Migrate tests/CreatesApplication.php and tests/TestCase.php manually before using --structure=modern.',
                'tests/TestCase.php',
            );

            $creates = $projectDirectory.'/tests/CreatesApplication.php';
            $baseCreates = $from.'/tests/CreatesApplication.php';
            $createsSource = is_file($creates) ? file_get_contents($creates) : false;
            $baseCreatesSource = is_file($baseCreates) ? file_get_contents($baseCreates) : false;

            if ($createsSource !== false && $baseCreatesSource !== false && $createsSource !== $baseCreatesSource) {
                $this->unsafe(
                    $collector,
                    $result,
                    'Customized tests/CreatesApplication.php cannot be safely retired.',
                    'Keep the customized trait and its TestCase use until the test bootstrap has been migrated manually.',
                    'tests/CreatesApplication.php',
                );
            }

            $safe = false;
        }

        $configPath = $projectDirectory.'/config/app.php';

        if (is_file($configPath)) {
            $config = file_get_contents($configPath);

            if ($config === false || $this->providerClasses($config) === null) {
                $this->unsafe(
                    $collector,
                    $result,
                    'The application provider list contains dynamic or unsupported entries.',
                    'Move every explicit provider into bootstrap/providers.php manually before using --structure=modern.',
                    'config/app.php',
                );
                $safe = false;
            }
        }

        return $safe;
    }

    /** @param MigrationResult $result */
    private function unsafe(
        ?FindingCollector $collector,
        array &$result,
        string $message,
        string $action,
        string $file,
    ): void {
        $this->finding($collector, Finding::SEVERITY_HIGH, $message, $action, 11, $file);

        if (! in_array($file, $result['conflicts'], true)) {
            $result['conflicts'][] = $file;
        }
    }

    private function safeKernelClass(Class_ $class, string $source): bool
    {
        if ($class->extends === null || $this->importedName($source, $class->extends) !== 'Illuminate\\Foundation\\Http\\Kernel') {
            return false;
        }

        $names = ['middleware', 'middlewareGroups', 'middlewareAliases', 'routeMiddleware', 'middlewarePriority'];
        $seen = [];

        foreach ($class->stmts as $statement) {
            if (! $statement instanceof Property || count($statement->props) !== 1
                || ! in_array($statement->props[0]->name->toString(), $names, true)
                || ! $statement->props[0]->default instanceof Array_) {
                return false;
            }

            $name = $statement->props[0]->name->toString();

            if (isset($seen[$name])) {
                return false;
            }

            $seen[$name] = true;
            $array = $statement->props[0]->default;

            foreach ($array->items as $item) {
                if (! $item instanceof ArrayItem || $item->unpack) {
                    return false;
                }

                if ($name === 'middlewareGroups') {
                    if (! $item->key instanceof String_ || ! $item->value instanceof Array_
                        || ! $this->safeMiddlewareArray($item->value, $source)) {
                        return false;
                    }

                    continue;
                }

                if ($name === 'middlewareAliases' || $name === 'routeMiddleware') {
                    if (! $item->key instanceof String_
                        || ! $this->safeMiddlewareExpression($item->value, $source)) {
                        return false;
                    }

                    continue;
                }

                if ($item->key !== null || ! $this->safeMiddlewareExpression($item->value, $source)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function safeMiddlewareArray(Array_ $array, string $source): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $item->key !== null || $item->unpack
                || ! $this->safeMiddlewareExpression($item->value, $source)) {
                return false;
            }
        }

        return true;
    }

    private function safeMiddlewareExpression(Node $expression, string $source): bool
    {
        if ($expression instanceof String_) {
            return true;
        }

        if ($expression instanceof ClassConstFetch) {
            return $expression->name instanceof Identifier && $expression->name->toString() === 'class'
                && $expression->class instanceof Name
                && $this->resolveNodeImports($expression, $source);
        }

        return $expression instanceof Concat
            && $expression->left instanceof ClassConstFetch
            && $expression->left->name instanceof Identifier
            && $expression->left->name->toString() === 'class'
            && $expression->left->class instanceof Name
            && $expression->right instanceof String_
            && $this->resolveNodeImports($expression, $source);
    }

    private function safeExceptionArray(Array_ $array, string $source): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $item->key !== null || $item->unpack
                || ! $item->value instanceof String_ && ! $item->value instanceof ClassConstFetch
                || ! $this->resolveNodeImports($item->value, $source)) {
                return false;
            }
        }

        return true;
    }

    private function safeHandlerClass(Class_ $class, string $source): bool
    {
        if ($class->extends === null
            || $this->importedName($source, $class->extends) !== 'Illuminate\\Foundation\\Exceptions\\Handler') {
            return false;
        }

        foreach ($class->stmts as $statement) {
            if ($statement instanceof Property) {
                if (count($statement->props) !== 1 || ! in_array($statement->props[0]->name->toString(), ['dontReport', 'dontFlash'], true)
                    || ! $statement->props[0]->default instanceof Array_
                    || ! $this->safeExceptionArray($statement->props[0]->default, $source)) {
                    return false;
                }

                continue;
            }

            if (! $statement instanceof ClassMethod || $statement->name->toString() !== 'register'
                || $statement->flags !== Modifiers::PUBLIC || $statement->params !== []
                || ! $statement->returnType instanceof Identifier || $statement->returnType->toString() !== 'void'
                || $this->exceptionCallbackStatements($statement, $source) === null) {
                return false;
            }
        }

        return true;
    }

    private function safeConsoleClass(Class_ $class, string $from, string $to, string $projectDirectory, string $source): bool
    {
        if ($class->extends === null
            || $this->importedName($source, $class->extends) !== 'Illuminate\\Foundation\\Console\\Kernel') {
            return false;
        }

        $schedule = $this->method($class, 'schedule');
        $commands = $this->method($class, 'commands');
        $basePath = $from.'/app/Console/Kernel.php';
        $baseSource = is_file($basePath) ? file_get_contents($basePath) : false;
        $baseClass = is_string($baseSource) ? $this->class($baseSource, 'app/Console/Kernel.php', null, 11) : null;
        $baseCommands = $baseClass === null ? null : $this->method($baseClass, 'commands');

        if ($schedule === null || $commands === null || $baseCommands === null || ! $this->sameStatements($commands, $baseCommands)
            || ! $this->safeScheduleMethod($schedule, $source)) {
            return false;
        }

        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod && in_array($statement->name->toString(), ['schedule', 'commands'], true)) {
                continue;
            }

            return false;
        }

        if ($schedule->stmts !== null && $this->printer->prettyPrint($schedule->stmts) !== '') {
            return is_file($projectDirectory.'/routes/console.php') || is_file($to.'/routes/console.php');
        }

        return true;
    }

    private function safeScheduleMethod(ClassMethod $method, string $source): bool
    {
        if ($method->stmts === null) {
            return true;
        }

        foreach ($method->stmts as $statement) {
            if ($statement instanceof Nop) {
                continue;
            }

            $variables = $this->finder->findInstanceOf($statement, Variable::class);

            if (! $statement instanceof Expression || ! $statement->expr instanceof MethodCall
                || ! $this->scheduleChain($statement->expr)
                || count($variables) !== 1
                || $variables[0]->name !== 'schedule'
                || $this->finder->find($statement, static fn (Node $node): bool => $node instanceof If_ || $node instanceof Foreach_ || $node instanceof For_ || $node instanceof While_ || $node instanceof FuncCall || $node instanceof Variable && $node->name !== 'schedule') !== []
                || ! $this->resolveNodeImports($statement, $source)) {
                return false;
            }
        }

        return true;
    }

    private function scheduleChain(MethodCall $call): bool
    {
        $receiver = $call;

        while ($receiver instanceof MethodCall) {
            if (! $receiver->name instanceof Identifier) {
                return false;
            }

            $receiver = $receiver->var;
        }

        return $receiver instanceof Variable && $receiver->name === 'schedule';
    }

    private function safeRouteProviderClass(Class_ $class, string $source, string $projectDirectory, string $to): bool
    {
        if (! $this->expectedRouteProviderParent($class, $source)) {
            return false;
        }

        $boot = $this->method($class, 'boot');

        if ($boot === null || $boot->stmts === null || $boot->flags !== Modifiers::PUBLIC
            || $boot->params !== [] || ! $boot->returnType instanceof Identifier
            || $boot->returnType->toString() !== 'void') {
            return false;
        }

        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassConst) {
                if (count($statement->consts) !== 1
                    || ! $statement->consts[0] instanceof Const_
                    || $statement->consts[0]->name->toString() !== 'HOME'
                    || ! $statement->consts[0]->value instanceof String_
                    || $statement->consts[0]->value->value !== '/home'
                    || $statement->flags !== Modifiers::PUBLIC) {
                    return false;
                }

                continue;
            }

            if (! $statement instanceof ClassMethod || $statement->name->toString() !== 'boot') {
                return false;
            }
        }

        $routes = [];
        $rateLimiters = [];

        foreach ($boot->stmts as $statement) {
            if (! $statement instanceof Expression) {
                return false;
            }

            if ($statement->expr instanceof StaticCall && $this->safeRateLimiterCall($statement->expr, $source)) {
                $rateLimiters[] = $statement->expr;

                if (count($rateLimiters) > 1) {
                    return false;
                }

                continue;
            }

            if ($statement->expr instanceof MethodCall && $this->safeRoutesCall($statement->expr, $source)) {
                $patterns = $this->routePatterns($statement->expr, $source);

                if ($patterns === null) {
                    return false;
                }

                array_push($routes, ...$patterns);

                continue;
            }

            return false;
        }

        if ($routes !== ['api', 'web']) {
            return false;
        }

        $hasRateLimiter = $rateLimiters !== [];

        if ($hasRateLimiter && ! is_file($projectDirectory.'/app/Providers/AppServiceProvider.php')
            && ! is_file($to.'/app/Providers/AppServiceProvider.php')) {
            return false;
        }

        return true;
    }

    private function expectedRouteProviderParent(Class_ $class, string $source): bool
    {
        return $class->extends !== null
            && $this->importedName($source, $class->extends) === 'Illuminate\\Foundation\\Support\\Providers\\RouteServiceProvider';
    }

    private function importedName(string $source, Name $name): ?string
    {
        $value = ltrim($name->toString(), '\\');

        if ($name->isFullyQualified()) {
            return $value;
        }

        try {
            $nodes = $this->parser->parse($source) ?? [];
        } catch (Throwable) {
            return null;
        }

        foreach ($this->finder->findInstanceOf($nodes, Use_::class) as $node) {
            foreach ($node->uses as $use) {
                $import = ltrim($use->name->toString(), '\\');
                $alias = $use->alias?->toString() ?? basename(str_replace('\\', '/', $import));

                if ($alias === $value) {
                    return $import;
                }
            }
        }

        return null;
    }

    private function resolveNodeImports(Node $node, string $source): bool
    {
        foreach ($this->finder->find($node, static fn (Node $candidate): bool => $candidate instanceof ClassConstFetch
            || $candidate instanceof StaticCall
            || $candidate instanceof New_
            || $candidate instanceof Instanceof_
            || $candidate instanceof Param
            || $candidate instanceof Closure
            || $candidate instanceof Catch_) as $candidate) {
            if ($candidate instanceof Param) {
                if (! $candidate->type instanceof Name) {
                    continue;
                }

                $resolved = $this->importedName($source, $candidate->type);

                if ($resolved === null) {
                    return false;
                }

                $candidate->type = new FullyQualified($resolved);

                continue;
            }

            if ($candidate instanceof Closure) {
                if (! $candidate->returnType instanceof Name) {
                    continue;
                }

                $resolved = $this->importedName($source, $candidate->returnType);

                if ($resolved === null) {
                    return false;
                }

                $candidate->returnType = new FullyQualified($resolved);

                continue;
            }

            if ($candidate instanceof Catch_) {
                foreach ($candidate->types as $index => $type) {
                    $resolved = $this->importedName($source, $type);

                    if ($resolved === null) {
                        return false;
                    }

                    $candidate->types[$index] = new FullyQualified($resolved);
                }

                continue;
            }

            if ($candidate instanceof ClassConstFetch) {
                if (! $candidate->class instanceof Name) {
                    return false;
                }

                $resolved = $this->importedName($source, $candidate->class);

                if ($resolved === null) {
                    return false;
                }

                $candidate->class = new FullyQualified($resolved);
            } elseif ($candidate instanceof StaticCall) {
                if (! $candidate->class instanceof Name) {
                    return false;
                }

                $resolved = $this->importedName($source, $candidate->class);

                if ($resolved === null) {
                    return false;
                }

                $candidate->class = new FullyQualified($resolved);
            } elseif ($candidate instanceof New_) {
                if (! $candidate->class instanceof Name) {
                    return false;
                }

                $resolved = $this->importedName($source, $candidate->class);

                if ($resolved === null) {
                    return false;
                }

                $candidate->class = new FullyQualified($resolved);
            } elseif ($candidate instanceof Instanceof_) {
                if (! $candidate->class instanceof Name) {
                    return false;
                }

                $resolved = $this->importedName($source, $candidate->class);

                if ($resolved === null) {
                    return false;
                }

                $candidate->class = new FullyQualified($resolved);
            }
        }

        return true;
    }

    /** @param array<int|string, Node> $nodes */
    private function resolvedPrettyPrint(array $nodes, string $source): ?string
    {
        foreach ($nodes as $node) {
            if (! $this->resolveNodeImports($node, $source)) {
                return null;
            }
        }

        return $this->printer->prettyPrint($nodes);
    }

    private function resolveExpressionImports(string $expression, string $source): ?string
    {
        try {
            $nodes = $this->parser->parse("<?php\nreturn {$expression};") ?? [];
        } catch (Throwable) {
            return null;
        }

        $return = $this->finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof Return_);

        if (! $return instanceof Return_ || $return->expr === null || ! $this->resolveNodeImports($return->expr, $source)) {
            return null;
        }

        return $this->printer->prettyPrintExpr($return->expr);
    }

    /** @return list<string> */
    private function unexpectedRouteFiles(string $projectDirectory): array
    {
        $routesDirectory = $projectDirectory.'/routes';
        $allowed = ['routes/api.php', 'routes/channels.php', 'routes/console.php', 'routes/web.php'];
        $unexpected = [];

        if (! is_dir($routesDirectory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($routesDirectory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($projectDirectory) + 1));

            if (! in_array($relative, $allowed, true)) {
                $unexpected[] = $relative;
            }
        }

        sort($unexpected);

        return $unexpected;
    }

    private function safeRateLimiterCall(StaticCall $call, string $source): bool
    {
        if (! $call->class instanceof Name || ! $call->name instanceof Identifier
            || $call->name->toString() !== 'for'
            || $this->importedName($source, $call->class) !== 'Illuminate\\Support\\Facades\\RateLimiter'
            || count($call->args) !== 2 || ! $call->args[0] instanceof Arg || ! $call->args[0]->value instanceof String_
            || $call->args[0]->name !== null || $call->args[0]->unpack
            || ! $call->args[1] instanceof Arg || $call->args[1]->name !== null || $call->args[1]->unpack
            || ! $call->args[1]->value instanceof Closure) {
            return false;
        }

        $parameter = $call->args[1]->value->params[0] ?? null;

        if ($call->args[0]->value->value !== 'api' || count($call->args[1]->value->params) !== 1
            || $parameter === null || ! $parameter->var instanceof Variable
            || $parameter->var->name !== 'request'
            || $parameter->byRef || $parameter->variadic || ! $parameter->type instanceof Name
            || $this->importedName($source, $parameter->type) !== 'Illuminate\\Http\\Request'
            || count($call->args[1]->value->stmts ?? []) !== 1
            || ! $call->args[1]->value->stmts[0] instanceof Return_
            || ! $call->args[1]->value->stmts[0]->expr instanceof MethodCall) {
            return false;
        }

        $by = $call->args[1]->value->stmts[0]->expr;

        if (! $by->name instanceof Identifier || $by->name->toString() !== 'by' || count($by->args) !== 1
            || ! $by->args[0] instanceof Arg || $by->args[0]->name !== null || $by->args[0]->unpack
            || ! $by->var instanceof StaticCall
            || ! $by->var->class instanceof Name
            || $this->importedName($source, $by->var->class) !== 'Illuminate\\Cache\\RateLimiting\\Limit'
            || ! $by->var->name instanceof Identifier || $by->var->name->toString() !== 'perMinute'
            || count($by->var->args) !== 1 || ! $by->var->args[0] instanceof Arg
            || ! $by->var->args[0]->value instanceof Int_) {
            return false;
        }

        $value = $by->args[0]->value;

        if ($this->finder->findInstanceOf($value, FuncCall::class) !== []
            || $this->finder->findInstanceOf($value, StaticCall::class) !== []) {
            return false;
        }

        foreach ($this->finder->findInstanceOf($value, MethodCall::class) as $method) {
            if (! $method->var instanceof Variable || $method->var->name !== 'request'
                || ! $method->name instanceof Identifier || ! in_array($method->name->toString(), ['user', 'ip'], true)
                || $method->args !== []) {
                return false;
            }
        }

        foreach ($this->finder->findInstanceOf($value, Variable::class) as $variable) {
            if ($variable->name !== 'request') {
                return false;
            }
        }

        return $this->resolveNodeImports($call, $source);
    }

    private function safeRoutesCall(MethodCall $call, string $source): bool
    {
        return $this->routePatterns($call, $source) !== null;
    }

    /** @return list<string>|null */
    private function routePatterns(MethodCall $call, string $source): ?array
    {
        if (! $call->var instanceof Variable || $call->var->name !== 'this'
            || ! $call->name instanceof Identifier || $call->name->toString() !== 'routes'
            || count($call->args) !== 1 || ! $call->args[0] instanceof Arg || ! $call->args[0]->value instanceof Closure) {
            return null;
        }

        $patterns = [];

        foreach ($call->args[0]->value->stmts ?? [] as $statement) {
            if ($statement instanceof Nop) {
                continue;
            }

            if (! $statement instanceof Expression || ! $statement->expr instanceof MethodCall) {
                return null;
            }

            $pattern = $this->routePattern($statement->expr, $source);

            if ($pattern === null) {
                return null;
            }

            $patterns[] = $pattern;
        }

        return $patterns;
    }

    private function routePattern(MethodCall $call, string $source): ?string
    {
        $receiver = $call;
        /** @var list<array{string, list<Arg>}> $chain */
        $chain = [];

        while ($receiver instanceof MethodCall) {
            if (! $receiver->name instanceof Identifier) {
                return null;
            }

            $chain[] = [$receiver->name->toString(), $receiver->args];
            $receiver = $receiver->var;
        }

        if (! $receiver instanceof StaticCall || ! $receiver->class instanceof Name
            || $this->importedName($source, $receiver->class) !== 'Illuminate\\Support\\Facades\\Route'
            || ! $receiver->name instanceof Identifier || $receiver->name->toString() !== 'middleware'
            || count($receiver->args) !== 1 || ! $receiver->args[0] instanceof Arg
            || $receiver->args[0]->name !== null || $receiver->args[0]->unpack
            || ! $receiver->args[0]->value instanceof String_) {
            return null;
        }

        $middleware = $receiver->args[0]->value->value;
        $group = $chain[0];

        if ($group[0] !== 'group' || count($group[1]) !== 1
            || ! $group[1][0] instanceof Arg || $group[1][0]->name !== null || $group[1][0]->unpack
            || ! $this->routeFile($group[1][0]->value, $middleware)) {
            return null;
        }

        if ($middleware === 'web') {
            return count($chain) === 1 ? 'web' : null;
        }

        $prefix = $chain[1] ?? null;

        if ($middleware !== 'api' || ! is_array($prefix) || $prefix[0] !== 'prefix'
            || count($prefix[1]) !== 1 || ! $prefix[1][0] instanceof Arg
            || $prefix[1][0]->name !== null || $prefix[1][0]->unpack
            || ! $prefix[1][0]->value instanceof String_ || $prefix[1][0]->value->value !== 'api'
            || count($chain) !== 2) {
            return null;
        }

        return 'api';
    }

    private function routeFile(Node $expression, string $middleware): bool
    {
        if (! $expression instanceof FuncCall || ! $expression->name instanceof Name
            || $expression->name->toString() !== 'base_path' || count($expression->args) !== 1
            || ! $expression->args[0] instanceof Arg || ! $expression->args[0]->value instanceof String_) {
            return false;
        }

        return $expression->args[0]->value->value === 'routes/'.$middleware.'.php';
    }

    /** @return list<string> */
    private function routeRateLimiterStatements(ClassMethod $boot, string $source): array
    {
        $statements = [];

        foreach ($boot->stmts ?? [] as $statement) {
            if ($statement instanceof Expression && $statement->expr instanceof StaticCall
                && $this->safeRateLimiterCall($statement->expr, $source)) {
                if (! $this->resolveNodeImports($statement, $source)) {
                    continue;
                }

                $statements[] = $this->printer->prettyPrint([$statement]);
            }
        }

        return $statements;
    }

    private function safeTestBootstrap(string $projectDirectory, string $from, string $to): bool
    {
        $creates = $projectDirectory.'/tests/CreatesApplication.php';
        $baseCreates = $from.'/tests/CreatesApplication.php';

        if (is_file($creates)) {
            $source = file_get_contents($creates);
            $base = is_file($baseCreates) ? file_get_contents($baseCreates) : false;

            if ($source === false || $base === false || $source !== $base) {
                return false;
            }
        }

        $testCase = $projectDirectory.'/tests/TestCase.php';

        if (! is_file($testCase)) {
            return true;
        }

        $source = file_get_contents($testCase);
        $base = is_file($from.'/tests/TestCase.php') ? file_get_contents($from.'/tests/TestCase.php') : false;
        $target = is_file($to.'/tests/TestCase.php') ? file_get_contents($to.'/tests/TestCase.php') : false;

        return $source !== false && $base !== false && $target !== false && ($source === $base || $source === $target);
    }

    private function middlewareCallback(Class_ $kernel, string $projectDirectory, string $from, string $source): string
    {
        $body = [];
        $global = $this->normalizeMiddlewareValues($this->propertyValues($kernel, ['middleware']), $projectDirectory, $from, $source);

        if ($global !== [] && $global !== self::DEFAULT_GLOBAL) {
            $body[] = '$middleware->use('.$this->arrayLiteral($global, 0).');';
        }

        foreach (['web', 'api'] as $group) {
            $groups = $this->propertyMapValues($kernel, 'middlewareGroups');
            $values = array_key_exists($group, $groups) ? $groups[$group] : null;

            if ($values !== null) {
                $values = $this->normalizeMiddlewareValues($values, $projectDirectory, $from, $source);
            }

            if ($values === null) {
                continue;
            }

            $default = self::DEFAULT_GROUPS[$group];
            if ($values === $default) {
                continue;
            }

            // A complete group definition preserves arbitrary user ordering,
            // including middleware with parameters, without ambiguous
            // replace maps.
            $body[] = '$middleware->group('.var_export($group, true).', '.$this->arrayLiteral($values, 0).');';
        }

        $groups = $this->propertyMapValues($kernel, 'middlewareGroups');

        foreach ($groups as $name => $values) {
            if (isset(self::DEFAULT_GROUPS[$name])) {
                continue;
            }

            $values = $this->normalizeMiddlewareValues($values, $projectDirectory, $from, $source);
            $body[] = '$middleware->group('.var_export($name, true).', '.$this->arrayLiteral($values, 0).');';
        }

        $aliases = array_merge(
            $this->propertyAliasValues($kernel, 'routeMiddleware'),
            $this->propertyAliasValues($kernel, 'middlewareAliases'),
        );

        foreach ($aliases as $name => $value) {
            $aliases[$name] = $this->normalizeMiddlewareReference($value, $projectDirectory, $from, $source);
        }

        $customAliases = [];

        foreach ($aliases as $name => $value) {
            if (! array_key_exists($name, self::DEFAULT_ALIASES) || self::DEFAULT_ALIASES[$name] !== $value) {
                $customAliases[$name] = $value;
            }
        }

        if ($customAliases !== []) {
            $body[] = '$middleware->alias('.$this->mapLiteral($customAliases, 0).');';
        }

        $priority = $this->normalizeMiddlewareValues($this->propertyValues($kernel, ['middlewarePriority']), $projectDirectory, $from, $source);

        if ($priority !== []) {
            $body[] = '$middleware->priority('.$this->arrayLiteral($priority, 0).');';
        }

        return implode("\n\n", $body);
    }

    /** @param list<string> $values
     * @return list<string>
     */
    private function normalizeMiddlewareValues(array $values, string $projectDirectory, string $from, string $source): array
    {
        return array_map(
            fn (string $value): string => $this->normalizeMiddlewareReference($value, $projectDirectory, $from, $source),
            $values,
        );
    }

    private function normalizeMiddlewareReference(string $value, string $projectDirectory, string $from, string $source): string
    {
        $value = $this->resolveExpressionImports($value, $source) ?? $value;
        $map = [
            '\\App\\Http\\Middleware\\Authenticate::class' => ['Authenticate.php', '\\Illuminate\\Auth\\Middleware\\Authenticate::class'],
            '\\App\\Http\\Middleware\\EncryptCookies::class' => ['EncryptCookies.php', '\\Illuminate\\Cookie\\Middleware\\EncryptCookies::class'],
            '\\App\\Http\\Middleware\\PreventRequestsDuringMaintenance::class' => ['PreventRequestsDuringMaintenance.php', '\\Illuminate\\Foundation\\Http\\Middleware\\PreventRequestsDuringMaintenance::class'],
            '\\App\\Http\\Middleware\\RedirectIfAuthenticated::class' => ['RedirectIfAuthenticated.php', '\\Illuminate\\Auth\\Middleware\\RedirectIfAuthenticated::class'],
            '\\App\\Http\\Middleware\\TrimStrings::class' => ['TrimStrings.php', '\\Illuminate\\Foundation\\Http\\Middleware\\TrimStrings::class'],
            '\\App\\Http\\Middleware\\TrustHosts::class' => ['TrustHosts.php', '\\Illuminate\\Http\\Middleware\\TrustHosts::class'],
            '\\App\\Http\\Middleware\\TrustProxies::class' => ['TrustProxies.php', '\\Illuminate\\Http\\Middleware\\TrustProxies::class'],
            '\\App\\Http\\Middleware\\ValidateSignature::class' => ['ValidateSignature.php', '\\Illuminate\\Routing\\Middleware\\ValidateSignature::class'],
            '\\App\\Http\\Middleware\\VerifyCsrfToken::class' => ['VerifyCsrfToken.php', '\\Illuminate\\Foundation\\Http\\Middleware\\ValidateCsrfToken::class'],
        ];
        $entry = $map[$value] ?? null;

        if ($entry === null) {
            return $value;
        }

        [$file, $replacement] = $entry;
        $project = file_get_contents($projectDirectory.'/app/Http/Middleware/'.$file);
        $base = file_get_contents($from.'/app/Http/Middleware/'.$file);

        return $project !== false && $base !== false && $project === $base ? $replacement : $value;
    }

    /** @param MigrationResult $result */
    private function removeDefaultMiddleware(
        string $projectDirectory,
        string $from,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
    ): void {
        foreach ([
            'Authenticate.php',
            'EncryptCookies.php',
            'PreventRequestsDuringMaintenance.php',
            'RedirectIfAuthenticated.php',
            'TrimStrings.php',
            'TrustHosts.php',
            'TrustProxies.php',
            'ValidateSignature.php',
            'VerifyCsrfToken.php',
        ] as $file) {
            $relative = 'app/Http/Middleware/'.$file;
            $this->deleteIdentical(
                $projectDirectory.'/'.$relative,
                $from.'/'.$relative,
                $relative,
                $projectDirectory,
                $collector,
                11,
                $dryRun,
                $result,
            );
        }
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function propertyValues(Class_ $class, array $names): array
    {
        foreach ($class->stmts as $statement) {
            if (! $statement instanceof Property) {
                continue;
            }

            foreach ($statement->props as $property) {
                if (! in_array($property->name->toString(), $names, true) || ! $property->default instanceof Array_) {
                    continue;
                }

                return $this->arrayValues($property->default);
            }
        }

        return [];
    }

    /** @return array<string, list<string>> */
    private function propertyMapValues(Class_ $class, string $name): array
    {
        foreach ($class->stmts as $statement) {
            if (! $statement instanceof Property) {
                continue;
            }

            foreach ($statement->props as $property) {
                if ($property->name->toString() !== $name || ! $property->default instanceof Array_) {
                    continue;
                }

                $result = [];

                foreach ($property->default->items as $item) {
                    if (! $item instanceof ArrayItem || $item->key === null || ! $item->value instanceof Array_) {
                        continue;
                    }

                    $key = $this->expression($item->key);
                    $key = trim($key, "'\"");
                    $result[$key] = $this->arrayValues($item->value);
                }

                return $result;
            }
        }

        return [];
    }

    /** @return array<string, string> */
    private function propertyAliasValues(Class_ $class, string $name): array
    {
        foreach ($class->stmts as $statement) {
            if (! $statement instanceof Property) {
                continue;
            }

            foreach ($statement->props as $property) {
                if ($property->name->toString() !== $name || ! $property->default instanceof Array_) {
                    continue;
                }

                $result = [];

                foreach ($property->default->items as $item) {
                    if (! $item instanceof ArrayItem || $item->key === null) {
                        continue;
                    }

                    $result[trim($this->expression($item->key), "'\"")] = $this->expression($item->value);
                }

                return $result;
            }
        }

        return [];
    }

    /** @return list<string> */
    private function arrayValues(Array_ $array): array
    {
        $values = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem) {
                continue;
            }

            $values[] = $this->expression($item->value);
        }

        return $values;
    }

    /** @return list<string>|null */
    private function resolvedArrayValues(Array_ $array, string $source): ?array
    {
        $values = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $item->key !== null || $item->unpack
                || ! $this->resolveNodeImports($item->value, $source)) {
                return null;
            }

            $values[] = $this->expression($item->value);
        }

        return $values;
    }

    private function expression(Expr $expression): string
    {
        return $this->printer->prettyPrintExpr($expression);
    }

    /** @param list<string> $values */
    private function arrayLiteral(array $values, int $indent): string
    {
        if ($values === []) {
            return '[]';
        }

        $padding = str_repeat('    ', $indent);
        $itemPadding = str_repeat('    ', $indent + 1);

        return "[\n".$itemPadding.implode(",\n".$itemPadding, $values).",\n".$padding.']';
    }

    /** @param array<string, string> $values */
    private function mapLiteral(array $values, int $indent): string
    {
        if ($values === []) {
            return '[]';
        }

        $items = [];

        foreach ($values as $key => $value) {
            $items[] = var_export($key, true).' => '.$value;
        }

        return $this->arrayLiteral($items, $indent);
    }

    private function replaceCallback(string $source, string $method, string $signature, string $body): string
    {
        $needle = '->'.$method.'(function ('.$signature.') {';

        if (! str_contains($source, $needle) || $body === '') {
            return $source;
        }

        $needlePosition = strpos($source, $needle);

        if ($needlePosition === false) {
            return $source;
        }

        $opening = strpos($source, '{', $needlePosition);

        if ($opening === false) {
            return $source;
        }

        $closing = $this->matchingBrace($source, $opening);

        if ($closing === false) {
            return $source;
        }

        $existing = substr($source, $opening + 1, $closing - $opening - 1);

        if (str_contains($existing, trim($body))) {
            return $source;
        }

        $formatted = str_replace("\n", "\n        ", trim($body));
        $prefix = rtrim(substr($source, 0, $closing));
        $suffix = substr($source, $closing);

        return $prefix."\n        ".$formatted."\n    ".$suffix;
    }

    /** @param MigrationResult $result */
    private function migrateExceptions(
        string $projectDirectory,
        string $from,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
        ?string &$bootstrap,
    ): void {
        $path = $projectDirectory.'/app/Exceptions/Handler.php';
        $base = $from.'/app/Exceptions/Handler.php';

        if (! is_file($path)) {
            return;
        }

        $source = file_get_contents($path);

        if ($source === false) {
            return;
        }

        $class = $this->class($source, 'app/Exceptions/Handler.php', $collector, 11);

        if ($class === null) {
            return;
        }

        if ($bootstrap === null) {
            return;
        }

        $body = [];
        $custom = false;

        foreach ($class->stmts as $statement) {
            if ($statement instanceof Property) {
                if (count($statement->props) !== 1) {
                    $custom = true;

                    continue;
                }

                $name = $statement->props[0]->name->toString();

                if (in_array($name, ['dontReport', 'dontFlash'], true) && $statement->props[0]->default instanceof Array_) {
                    $method = $name === 'dontReport' ? 'dontReport' : 'dontFlash';
                    $values = $this->resolvedArrayValues($statement->props[0]->default, $source);

                    if ($values === null) {
                        $custom = true;
                    } else {
                        $body[] = '$exceptions->'.$method.'('.$this->arrayLiteral($values, 0).');';
                    }
                } else {
                    $custom = true;
                }
            }

            if ($statement instanceof ClassMethod && $statement->name->toString() !== 'register') {
                $custom = true;
            }
        }

        $register = $this->method($class, 'register');

        if ($register !== null && $register->stmts !== null) {
            $registerBody = $this->exceptionCallbackStatements($register, $source);

            if ($registerBody === null) {
                $custom = true;
            } elseif ($registerBody !== []) {
                $body = array_merge($body, $registerBody);
            }
        }

        if ($custom) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The customized exception Handler was not removed during modern structure migration.',
                'Move custom exception reporting/rendering behavior into bootstrap/app.php withExceptions manually.',
                11,
                'app/Exceptions/Handler.php',
            );
            $result['conflicts'][] = 'app/Exceptions/Handler.php';

            return;
        }

        if ($body !== []) {
            $exceptionBody = implode("\n\n", $body);
            $bootstrap = $this->replaceCallback($bootstrap, 'withExceptions', 'Exceptions $exceptions', $exceptionBody);
            $this->replaceFile($projectDirectory, $projectDirectory.'/bootstrap/app.php', $bootstrap, $from.'/bootstrap/app.php', $dryRun, $result);
        }

        $this->retireSafeComponent(
            $path,
            $base,
            'app/Exceptions/Handler.php',
            $projectDirectory,
            $collector,
            11,
            $dryRun,
            $result,
            $this->safeHandlerClass($class, $source),
        );
    }

    /** @param MigrationResult $result */
    private function migrateConsole(
        string $projectDirectory,
        string $from,
        string $to,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
    ): void {
        $path = $projectDirectory.'/app/Console/Kernel.php';
        $base = $from.'/app/Console/Kernel.php';

        if (! is_file($path)) {
            return;
        }

        $source = file_get_contents($path);

        if ($source === false) {
            return;
        }

        $class = $this->class($source, 'app/Console/Kernel.php', $collector, 11);

        if ($class === null) {
            return;
        }

        $custom = false;
        $schedule = $this->method($class, 'schedule');
        $commands = $this->method($class, 'commands');

        $baseSource = is_file($base) ? file_get_contents($base) : false;
        $baseClass = is_string($baseSource) ? $this->class($baseSource, 'app/Console/Kernel.php', null, 11) : null;
        $baseCommands = $baseClass === null ? null : $this->method($baseClass, 'commands');

        if ($commands === null || $baseCommands === null || ! $this->sameStatements($commands, $baseCommands)) {
            $custom = true;
        }

        if ($custom) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The customized console Kernel contains command registration that was not moved automatically.',
                'Move custom command registration to routes/console.php manually before removing the Kernel.',
                11,
                'app/Console/Kernel.php',
            );
            $result['conflicts'][] = 'app/Console/Kernel.php';

            return;
        }

        $scheduleBody = $schedule?->stmts === null ? '' : $this->resolvedPrettyPrint($schedule->stmts, $source);

        if ($scheduleBody === null) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The console schedule contains an unresolved imported class.',
                'Move the schedule to routes/console.php manually before removing the Kernel.',
                11,
                'app/Console/Kernel.php',
            );
            $result['conflicts'][] = 'app/Console/Kernel.php';

            return;
        }

        $scheduleBody = preg_replace('/^\s*\/\/.*$/m', '', $scheduleBody) ?? $scheduleBody;
        $scheduleBody = trim($scheduleBody);

        if ($scheduleBody !== '') {
            $scheduleBody = str_replace('$schedule->', 'Schedule::', $scheduleBody);
            $consolePath = $projectDirectory.'/routes/console.php';
            $console = is_file($consolePath) ? file_get_contents($consolePath) : (is_file($to.'/routes/console.php') ? file_get_contents($to.'/routes/console.php') : false);

            if ($console !== false) {
                $console = $this->addImport($console, 'Illuminate\\Support\\Facades\\Schedule');
                if (! str_contains($console, $scheduleBody)) {
                    $console = rtrim($console)."\n\n".$scheduleBody."\n";
                    $this->replaceFile($projectDirectory, $consolePath, $console, $to.'/routes/console.php', $dryRun, $result);
                }
            } else {
                $this->finding(
                    $collector,
                    Finding::SEVERITY_HIGH,
                    'The console schedule could not be copied because routes/console.php was unavailable.',
                    'Move the scheduled tasks to routes/console.php manually before removing the Kernel.',
                    11,
                    'app/Console/Kernel.php',
                );

                $result['conflicts'][] = 'app/Console/Kernel.php';

                return;
            }
        }

        if (! $this->retireSafeComponent(
            $path,
            $base,
            'app/Console/Kernel.php',
            $projectDirectory,
            $collector,
            11,
            $dryRun,
            $result,
            $this->safeConsoleClass($class, $from, $to, $projectDirectory, $source),
        )) {
            $result['conflicts'][] = 'app/Console/Kernel.php';
        }
    }

    /** @param MigrationResult $result */
    private function migrateRouting(
        string $projectDirectory,
        string $from,
        string $to,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
        string $bootstrapPath,
        ?string $bootstrap,
    ): void {
        $path = $projectDirectory.'/app/Providers/RouteServiceProvider.php';

        if (! is_file($path) || $bootstrap === null) {
            return;
        }

        $source = file_get_contents($path);

        if ($source === false) {
            return;
        }

        $class = $this->class($source, 'app/Providers/RouteServiceProvider.php', $collector, 11);

        if ($class === null) {
            return;
        }

        $boot = $this->method($class, 'boot');
        $bootBody = $boot?->stmts === null ? '' : $this->printer->prettyPrint($boot->stmts);
        $hasApi = str_contains($bootBody, 'routes/api.php') || str_contains($bootBody, 'routes/api');
        $prefix = 'api';

        if (preg_match("~->prefix\(\s*['\"]([^'\"]+)['\"]~", $bootBody, $match) === 1) {
            $prefix = $match[1];
        }

        if ($hasApi && ! str_contains($bootstrap, 'api: __DIR__')) {
            $bootstrap = str_replace(
                "        web: __DIR__.'/../routes/web.php',\n",
                "        web: __DIR__.'/../routes/web.php',\n        api: __DIR__.'/../routes/api.php',\n",
                $bootstrap,
            );

            if ($prefix !== 'api') {
                $bootstrap = str_replace(
                    "        api: __DIR__.'/../routes/api.php',\n",
                    "        api: __DIR__.'/../routes/api.php',\n        apiPrefix: ".var_export($prefix, true).",\n",
                    $bootstrap,
                );
            }
        }

        $rateLimiters = $boot instanceof ClassMethod ? $this->routeRateLimiterStatements($boot, $source) : [];

        if ($rateLimiters !== []) {
            $providerPath = $projectDirectory.'/app/Providers/AppServiceProvider.php';
            $provider = is_file($providerPath) ? file_get_contents($providerPath) : file_get_contents($to.'/app/Providers/AppServiceProvider.php');

            if ($provider !== false) {
                $provider = $this->addImport($provider, 'Illuminate\\Cache\\RateLimiting\\Limit');
                $provider = $this->addImport($provider, 'Illuminate\\Http\\Request');
                $provider = $this->addImport($provider, 'Illuminate\\Support\\Facades\\RateLimiter');

                foreach ($rateLimiters as $rateLimiter) {
                    if (! str_contains($provider, $rateLimiter)) {
                        $provider = $this->appendToMethod($provider, 'boot', $rateLimiter);
                    }
                }

                $this->replaceFile($projectDirectory, $providerPath, $provider, $to.'/app/Providers/AppServiceProvider.php', $dryRun, $result);
            }
        }

        $this->replaceFile($projectDirectory, $bootstrapPath, $bootstrap, $to.'/bootstrap/app.php', $dryRun, $result);
        if (! $this->retireSafeComponent(
            $path,
            $from.'/app/Providers/RouteServiceProvider.php',
            'app/Providers/RouteServiceProvider.php',
            $projectDirectory,
            $collector,
            11,
            $dryRun,
            $result,
            $this->safeRouteProviderClass($class, $source, $projectDirectory, $to),
        )) {
            $result['conflicts'][] = 'app/Providers/RouteServiceProvider.php';
        }
    }

    /** @return list<string>|null */
    private function providerClasses(string $source): ?array
    {
        try {
            $nodes = $this->parser->parse($source) ?? [];
        } catch (Throwable) {
            return null;
        }

        $uses = [];

        foreach ($nodes as $node) {
            if (! $node instanceof Use_) {
                continue;
            }

            foreach ($node->uses as $use) {
                $import = ltrim($use->name->toString(), '\\');
                $alias = $use->alias?->toString() ?? basename(str_replace('\\', '/', $import));
                $uses[$alias] = $import;
            }
        }

        $return = $this->finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof Return_);

        if (! $return instanceof Return_ || $return->expr === null) {
            return [];
        }

        if (! $return->expr instanceof Array_) {
            return null;
        }

        $providersExpression = null;

        foreach ($return->expr->items as $item) {
            if (! $item instanceof ArrayItem || $item->key === null) {
                continue;
            }

            if (trim($this->expression($item->key), "'\"") === 'providers') {
                $providersExpression = $item->value;
                break;
            }
        }

        if (! $providersExpression instanceof Node) {
            return [];
        }

        return $this->explicitProviderClasses($providersExpression, $uses);
    }

    /** @param array<string, string> $uses
     * @return list<string>|null
     */
    private function explicitProviderClasses(Node $expression, array $uses): ?array
    {
        if ($expression instanceof Array_) {
            $classes = [];

            foreach ($expression->items as $item) {
                if (! $item instanceof ArrayItem || ! $item->value instanceof ClassConstFetch
                    || ! $item->value->name instanceof Identifier || $item->value->name->toString() !== 'class'
                    || ! $item->value->class instanceof Name) {
                    return null;
                }

                $classes[] = $this->resolveProviderName($item->value->class, $uses);
            }

            return array_values(array_unique($classes));
        }

        if (! $expression instanceof MethodCall || ! $expression->name instanceof Identifier
            || $expression->name->toString() !== 'toArray' || $expression->args !== []
            || ! $expression->var instanceof MethodCall || ! $expression->var->name instanceof Identifier
            || $expression->var->name->toString() !== 'merge' || count($expression->var->args) !== 1
            || ! $expression->var->args[0] instanceof Arg || ! $expression->var->args[0]->value instanceof Array_
            || ! $expression->var->var instanceof StaticCall || ! $expression->var->var->name instanceof Identifier
            || $expression->var->var->name->toString() !== 'defaultProviders' || $expression->var->var->args !== []
            || ! $expression->var->var->class instanceof Name) {
            return null;
        }

        $class = $this->resolveProviderName($expression->var->var->class, $uses);

        if ($class !== 'Illuminate\\Support\\ServiceProvider') {
            return null;
        }

        return $this->explicitProviderClasses($expression->var->args[0]->value, $uses);
    }

    /** @param array<string, string> $uses */
    private function resolveProviderName(Name $name, array $uses): string
    {
        $value = ltrim($name->toString(), '\\');

        if ($name->isFullyQualified()) {
            return $value;
        }

        $parts = explode('\\', $value, 2);

        if (isset($uses[$parts[0]])) {
            return $uses[$parts[0]].(isset($parts[1]) ? '\\'.$parts[1] : '');
        }

        return $value;
    }

    /** @param MigrationResult $result */
    private function migrateProviders(
        string $projectDirectory,
        string $from,
        string $to,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
    ): void {
        $configPath = $projectDirectory.'/config/app.php';
        $targetProviders = $to.'/bootstrap/providers.php';

        if (! is_file($configPath) || ! is_file($targetProviders)) {
            return;
        }

        $config = file_get_contents($configPath);
        $providers = file_get_contents($targetProviders);

        if ($config === false || $providers === false) {
            return;
        }

        $classes = $this->providerClasses($config);

        if ($classes === null) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                'The application provider list contains dynamic or unsupported entries.',
                'Move every explicit provider into bootstrap/providers.php manually before using --structure=modern.',
                11,
                'config/app.php',
            );
            $result['conflicts'][] = 'config/app.php';

            return;
        }

        // A second modern pass reads the already extracted provider list from
        // bootstrap/providers.php. Do not replace it with the App provider
        // fallback merely because config/app.php no longer has a providers
        // key.
        if ($classes === [] && ! $this->configHasKey($config, 'providers')) {
            return;
        }

        $custom = [];

        foreach (array_unique($classes) as $class) {
            // RouteServiceProvider has already been retired by the routing
            // migration above. It must not be copied into bootstrap/providers
            // after its source file has been removed.
            if ($class === 'App\\Providers\\RouteServiceProvider') {
                continue;
            }

            $custom[] = $class;
        }

        if ($custom === []) {
            $custom = ['App\\Providers\\AppServiceProvider'];
        }

        $providers = preg_replace_callback(
            '/return \[.*?\];/s',
            fn (): string => "return [\n".implode("\n", array_map(static fn (string $class): string => '    '.$class.'::class,', $custom))."\n];",
            $providers,
            1,
        ) ?? $providers;

        $providerPath = $projectDirectory.'/bootstrap/providers.php';
        $this->replaceFile($projectDirectory, $providerPath, $providers, $targetProviders, $dryRun, $result);

        $withoutProviders = $this->removeProvidersConfiguration($config);

        if ($withoutProviders !== $config) {
            $this->replaceFile(
                $projectDirectory,
                $configPath,
                $withoutProviders,
                $from.'/config/app.php',
                $dryRun,
                $result,
            );
        }

        if ($this->configHasKey($config, 'aliases') && in_array('bootstrap/providers.php', $result['changed'], true)) {
            $this->finding(
                $collector,
                Finding::SEVERITY_MEDIUM,
                'config/app.php defines application aliases; they were left in place during modern structure migration.',
                'Verify custom aliases against Laravel 11 before removing or relocating the aliases block.',
                11,
                'config/app.php',
            );
        }
    }

    private function removeProvidersConfiguration(string $source): string
    {
        try {
            $nodes = $this->parser->parse($source) ?? [];
        } catch (Throwable) {
            return $source;
        }

        $return = $this->finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof Return_);

        if (! $return instanceof Return_ || ! $return->expr instanceof Array_) {
            return $source;
        }

        foreach ($return->expr->items as $item) {
            if (! $item instanceof ArrayItem || $item->key === null
                || trim($this->expression($item->key), "'\"") !== 'providers') {
                continue;
            }

            $start = $item->getStartFilePos();
            $end = $item->getEndFilePos() + 1;

            if ($start < 0 || $end <= $start) {
                return $source;
            }

            while ($end < strlen($source) && ctype_space($source[$end])) {
                $end++;
            }

            if (($source[$end] ?? '') === ',') {
                $end++;
            }

            return substr($source, 0, $start).substr($source, $end);
        }

        return $source;
    }

    private function configHasKey(string $source, string $key): bool
    {
        try {
            $nodes = $this->parser->parse($source) ?? [];
        } catch (Throwable) {
            return false;
        }

        $return = $this->finder->findFirst($nodes, static fn (Node $node): bool => $node instanceof Return_);

        if (! $return instanceof Return_ || ! $return->expr instanceof Array_) {
            return false;
        }

        foreach ($return->expr->items as $item) {
            if ($item instanceof ArrayItem && $item->key !== null && trim($this->expression($item->key), "'\"") === $key) {
                return true;
            }
        }

        return false;
    }

    /** @param MigrationResult $result */
    private function migrateTests(
        string $projectDirectory,
        string $from,
        string $to,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
    ): void {
        $creates = $projectDirectory.'/tests/CreatesApplication.php';
        $baseCreates = $from.'/tests/CreatesApplication.php';
        $createsSafe = false;

        if (is_file($creates)) {
            $createsSource = file_get_contents($creates);
            $baseCreatesSource = is_file($baseCreates) ? file_get_contents($baseCreates) : false;

            if ($createsSource === false || $baseCreatesSource === false || $createsSource !== $baseCreatesSource) {
                $this->finding(
                    $collector,
                    Finding::SEVERITY_HIGH,
                    'Customized tests/CreatesApplication.php was retained during modern structure migration.',
                    'Keep the trait and its TestCase use until the customized test bootstrap has been migrated manually.',
                    11,
                    'tests/CreatesApplication.php',
                );
                $result['conflicts'][] = 'tests/CreatesApplication.php';

                return;
            }

            $createsSafe = true;
        }

        $testCase = $projectDirectory.'/tests/TestCase.php';
        $baseTestCase = $from.'/tests/TestCase.php';
        $targetTestCase = $to.'/tests/TestCase.php';

        if (! is_file($testCase)) {
            if ($createsSafe) {
                $this->deleteIdentical($creates, $baseCreates, 'tests/CreatesApplication.php', $projectDirectory, $collector, 11, $dryRun, $result);
            }

            return;
        }

        if (! is_file($baseTestCase) || ! is_file($targetTestCase)) {
            return;
        }

        $project = file_get_contents($testCase);
        $base = file_get_contents($baseTestCase);
        $target = file_get_contents($targetTestCase);

        if ($project === false || $base === false || $target === false) {
            return;
        }

        if ($project === $target) {
            if ($createsSafe) {
                $this->deleteIdentical($creates, $baseCreates, 'tests/CreatesApplication.php', $projectDirectory, $collector, 11, $dryRun, $result);
            }

            return;
        }

        if ($project === $base) {
            $this->replaceFile($projectDirectory, $testCase, $target, $targetTestCase, $dryRun, $result);

            if ($createsSafe) {
                $this->deleteIdentical($creates, $baseCreates, 'tests/CreatesApplication.php', $projectDirectory, $collector, 11, $dryRun, $result);
            }

            return;
        }

        $this->finding(
            $collector,
            Finding::SEVERITY_MEDIUM,
            'Customized tests/TestCase.php was retained with its test bootstrap unchanged.',
            'Migrate the customized TestCase and CreatesApplication trait manually before removing the Laravel 10 test bootstrap.',
            11,
            'tests/TestCase.php',
        );
        $result['conflicts'][] = 'tests/TestCase.php';
    }

    /** @param MigrationResult $result */
    private function slimConfig(
        string $projectDirectory,
        string $from,
        ?FindingCollector $collector,
        bool $dryRun,
        array &$result,
        int $targetMajor,
    ): void {
        foreach (glob($projectDirectory.'/config/*.php') ?: [] as $path) {
            $relative = 'config/'.basename($path);
            $base = $from.'/'.$relative;

            if (! is_file($base)) {
                continue;
            }

            $projectContents = file_get_contents($path);
            $baseContents = file_get_contents($base);

            if ($projectContents === false || $baseContents === false || $projectContents !== $baseContents) {
                continue;
            }

            if (! $dryRun && ! unlink($path)) {
                throw new RuntimeException(sprintf('Could not remove unmodified config file "%s".', $relative));
            }

            $result['changed'][] = $relative;
            $result['deleted'][] = $relative;
            $this->finding(
                $collector,
                Finding::SEVERITY_INFO,
                sprintf('Laravel %d config slimming removed unmodified "%s".', $targetMajor, $relative),
                'The Laravel framework default is now used; restore the file if application-specific configuration is needed.',
                $targetMajor,
                $relative,
            );
        }
    }

    /** @param MigrationResult $result */
    private function replaceFile(
        string $projectDirectory,
        string $path,
        string $contents,
        string $sourcePath,
        bool $dryRun,
        array &$result,
    ): void {
        $relative = ltrim(str_replace('\\', '/', substr($path, strlen(rtrim($projectDirectory, '/')) + 1)), '/');
        $current = is_file($path) ? file_get_contents($path) : false;

        if ($current === $contents) {
            return;
        }

        $result['changed'][] = $relative;

        if ($dryRun) {
            return;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && (! mkdir($directory, 0777, true) && ! is_dir($directory))) {
            throw new RuntimeException(sprintf('Could not create directory "%s".', $directory));
        }

        $temporary = tempnam($directory, basename($path).'.tmp-');

        if ($temporary === false) {
            throw new RuntimeException(sprintf('Could not create temporary file for "%s".', $relative));
        }

        try {
            $written = file_put_contents($temporary, $contents, LOCK_EX);

            if ($written !== strlen($contents)) {
                throw new RuntimeException(sprintf('Could not write "%s".', $relative));
            }

            $mode = is_file($path) ? fileperms($path) : (is_file($sourcePath) ? fileperms($sourcePath) : 0644);
            chmod($temporary, ($mode === false ? 0644 : $mode) & 0777);

            if (! rename($temporary, $path)) {
                throw new RuntimeException(sprintf('Could not replace "%s".', $relative));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @param MigrationResult $result */
    private function deleteIdentical(
        string $path,
        string $basePath,
        string $relative,
        string $projectDirectory,
        ?FindingCollector $collector,
        int $major,
        bool $dryRun,
        array &$result,
    ): bool {
        if (! is_file($path) || ! is_file($basePath)) {
            return false;
        }

        $project = file_get_contents($path);
        $base = file_get_contents($basePath);

        if ($project === false || $base === false) {
            return false;
        }

        if ($project !== $base) {
            $this->finding(
                $collector,
                Finding::SEVERITY_HIGH,
                sprintf('Customized %s was retained during modern structure migration.', $relative),
                'Review and remove this file manually only after migrating all application behavior it contains.',
                $major,
                $relative,
            );

            return false;
        }

        $result['changed'][] = $relative;
        $result['deleted'][] = $relative;

        if (! $dryRun && ! unlink($path)) {
            throw new RuntimeException(sprintf('Could not remove "%s".', $relative));
        }

        return true;
    }

    /** @param MigrationResult $result */
    private function retireSafeComponent(
        string $path,
        string $basePath,
        string $relative,
        string $projectDirectory,
        ?FindingCollector $collector,
        int $major,
        bool $dryRun,
        array &$result,
        bool $behaviorSafe,
    ): bool {
        if (! $behaviorSafe) {
            return $this->deleteIdentical(
                $path,
                $basePath,
                $relative,
                $projectDirectory,
                $collector,
                $major,
                $dryRun,
                $result,
            );
        }

        if (! is_file($path)) {
            return false;
        }

        $result['changed'][] = $relative;
        $result['deleted'][] = $relative;

        if (! $dryRun && ! unlink($path)) {
            throw new RuntimeException(sprintf('Could not remove "%s".', $relative));
        }

        return true;
    }

    private function method(Class_ $class, string $name): ?ClassMethod
    {
        foreach ($class->stmts as $statement) {
            if ($statement instanceof ClassMethod && $statement->name->toString() === $name) {
                return $statement;
            }
        }

        return null;
    }

    private function sameStatements(ClassMethod $left, ClassMethod $right): bool
    {
        if ($left->stmts === null || $right->stmts === null) {
            return $left->stmts === $right->stmts;
        }

        return $this->printer->prettyPrint($left->stmts) === $this->printer->prettyPrint($right->stmts);
    }

    /**
     * Return only direct reportable/renderable Closure calls. Anything more
     * involved is intentionally left for a human because rewriting a
     * conditional or helper call can silently change exception behavior.
     *
     * @return list<string>|null
     */
    private function exceptionCallbackStatements(ClassMethod $register, string $source): ?array
    {
        if ($register->stmts === null) {
            return [];
        }

        $result = [];

        foreach ($register->stmts as $statement) {
            if (! $statement instanceof Expression || ! $statement->expr instanceof MethodCall) {
                return null;
            }

            $call = $statement->expr;

            $argument = $call->args[0] ?? null;

            if (! $call->var instanceof Variable || $call->var->name !== 'this'
                || ! $call->name instanceof Identifier || ! in_array($call->name->toString(), ['reportable', 'renderable'], true)
                || count($call->args) !== 1 || ! $argument instanceof Arg || $argument->name !== null || $argument->unpack
                || ! $argument->value instanceof Closure) {
                return null;
            }

            $closure = $argument->value;
            $parameters = [];

            foreach ($closure->params as $parameter) {
                if (! $parameter->var instanceof Variable || ! is_string($parameter->var->name)) {
                    return null;
                }

                $parameters[] = $parameter->var->name;
            }

            foreach ($this->finder->findInstanceOf($closure, Variable::class) as $variable) {
                if ($variable->name === 'this' || ! is_string($variable->name) || ! in_array($variable->name, $parameters, true)) {
                    return null;
                }
            }

            if (! $this->resolveNodeImports($closure, $source)) {
                return null;
            }

            $name = $call->name->toString();
            $replacement = $name === 'reportable' ? '$exceptions->report' : '$exceptions->render';
            $result[] = str_replace('$this->'.$name, $replacement, $this->printer->prettyPrint([$statement]));
        }

        return $result;
    }

    private function addImport(string $source, string $class): string
    {
        if (str_contains($source, 'use '.$class.';')) {
            return $source;
        }

        $namespace = preg_match('/^namespace [^;]+;\R/m', $source, $match, PREG_OFFSET_CAPTURE) === 1 ? $match[0] : null;

        if ($namespace !== null) {
            $offset = $namespace[1] + strlen($namespace[0]);

            return substr($source, 0, $offset)."\nuse ".$class.';'.substr($source, $offset);
        }

        return "<?php\n\nuse ".$class.";\n".ltrim($source, "<?php\n");
    }

    private function appendToMethod(string $source, string $method, string $statement): string
    {
        $offset = strpos($source, 'function '.$method);

        if ($offset === false) {
            $classEnd = strrpos($source, '}');

            if ($classEnd === false) {
                return $source;
            }

            return substr($source, 0, $classEnd)."\n    public function ".$method."(): void\n    {\n        ".str_replace("\n", "\n        ", $statement)."\n    }\n".substr($source, $classEnd);
        }

        $open = strpos($source, '{', $offset);
        $close = $open === false ? false : $this->matchingBrace($source, $open);

        if ($close === false) {
            return $source;
        }

        return substr($source, 0, $close)."\n        ".str_replace("\n", "\n        ", $statement)."\n    ".substr($source, $close);
    }

    private function matchingBrace(string $source, int $open): int|false
    {
        $depth = 0;
        $length = strlen($source);
        $quote = null;

        for ($index = $open; $index < $length; $index++) {
            $character = $source[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;

                continue;
            }

            if ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth === 0) {
                return $index;
            }
        }

        return false;
    }

    private function finding(
        ?FindingCollector $collector,
        string $severity,
        string $message,
        string $action,
        int $major,
        string $file,
    ): void {
        $collector?->add(
            'laravelUpgrade.modernStructure',
            $severity,
            $major,
            $file,
            0,
            $message,
            $action,
        );
    }
}
