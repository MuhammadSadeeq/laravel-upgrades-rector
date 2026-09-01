<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class AdvisoryRuleRegistrationTest extends TestCase
{
    public function test_laravel_11_neon_registers_only_existing_laravel_11_rules(): void
    {
        $path = dirname(__DIR__, 3).'/resources/phpstan/upgrade-11.neon';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        preg_match_all('/^\s*class:\s+([^\s]+)$/m', $contents, $matches);
        $classes = $matches[1];

        $expected = [
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ColumnChangeRequiresModifiersRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\EloquentCastsMethodConflictRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\SchemaGetColumnTypeRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\DoctrineRemovedMethodsRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\AuthenticationExceptionRedirectToRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\EnumerableDumpSignatureRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\EmailVerificationAutoRegistrationRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\PublishesConfigAppProvidersRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\PasswordRehashCustomColumnRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\CashierSubscriptionCancelEndsTrialRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\PassportRoutesRemovedRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\CarbonUntypedDiffRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\AfterCommitWithSyncQueueRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\MariaDbUuidColumnRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\FloatPrecisionDroppedRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\SpatialGeographyReviewRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\DiffForHumansOptionsRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\CarbonIntervalFloatSupportRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\SetTestNowCopySemanticsRule',
        ];

        self::assertSame($expected, $classes);

        foreach ($classes as $class) {
            self::assertTrue(class_exists($class), $class.' is registered but does not exist.');

            $reflection = new \ReflectionClass($class);
            self::assertTrue(
                $reflection->implementsInterface(Rule::class) || $reflection->isSubclassOf(Rule::class),
                $class.' is not a PHPStan rule.'
            );
        }

        foreach ([
            'HasUuidsNowV7Rule',
            'ImageRuleExcludesSvgRule',
            'ContainerCallNullableDefaultRule',
            'ModelBootInstantiationRule',
            'QueuedNotificationMissingModelsRule',
        ] as $class) {
            self::assertStringNotContainsString('Rules\\'.$class, $contents);
        }
    }

    public function test_major_neon_files_are_not_all_identical(): void
    {
        $files = array_map(
            static fn (int $major): string => dirname(__DIR__, 3).'/resources/phpstan/upgrade-'.$major.'.neon',
            [11, 12, 13]
        );
        $contents = array_map(static fn (string $path): string => (string) file_get_contents($path), $files);

        self::assertNotSame($contents[0], $contents[1]);
    }

    public function test_laravel_12_neon_registers_exactly_laravel_12_rules(): void
    {
        $path = dirname(__DIR__, 3).'/resources/phpstan/upgrade-12.neon';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        preg_match_all('/^\s*class:\s+([^\s]+)$/m', $contents, $matches);
        $classes = $matches[1];

        $expected = [
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\HasUuidsNowV7Rule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\BlueprintConstructorConnectionRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\GrammarConstructorRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\DatabaseTokenRepositoryExpiresRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ConcurrencyRunKeyedResultsRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ContainerDefaultParameterRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ImageRuleExcludesSvgRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\MergeIfMissingDotKeysRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\SchemaInspectionAllSchemasRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\LocalDiskDefaultRootRule',
        ];

        self::assertSame($expected, $classes);

        foreach ($classes as $class) {
            self::assertTrue(class_exists($class), $class.' is registered but does not exist.');

            $reflection = new \ReflectionClass($class);
            self::assertTrue(
                $reflection->implementsInterface(Rule::class) || $reflection->isSubclassOf(Rule::class),
                $class.' is not a PHPStan rule.'
            );
        }

        foreach ([
            'ColumnChangeRequiresModifiersRule',
            'EloquentCastsMethodConflictRule',
            'DoctrineRemovedMethodsRule',
            'PasswordResetSubjectRule',
            'ContainerCallNullableDefaultRule',
            'ModelBootInstantiationRule',
            'QueuedNotificationMissingModelsRule',
        ] as $class) {
            self::assertStringNotContainsString('Rules\\'.$class, $contents);
        }
    }

    public function test_laravel_12_packaged_neon_loads_without_generated_project_context(): void
    {
        $root = dirname(__DIR__, 3);
        $process = new Process([
            $root.'/vendor/bin/phpstan',
            'analyse',
            '-c',
            $root.'/resources/phpstan/upgrade-12.neon',
            $root.'/src/PHPStan/Rules/LocalDiskDefaultRootRule.php',
            '--no-progress',
            '--debug',
        ], $root);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }

    public function test_laravel_13_neon_registers_exactly_laravel_13_rules(): void
    {
        $path = dirname(__DIR__, 3).'/resources/phpstan/upgrade-13.neon';
        $contents = file_get_contents($path);

        self::assertIsString($contents);
        preg_match_all('/^\s*class:\s+([^\s]+)$/m', $contents, $matches);
        $classes = $matches[1];

        $expected = [
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ContainerCallNullableDefaultRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\UpsertEmptyUniqueByRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\DeleteJoinOrderLimitRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ModelBootInstantiationRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\MorphPivotTableNameRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\QueuedJobEloquentCollectionRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\QueuedNotificationMissingModelsRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\JobAttemptedExceptionTypeRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ManagerExtendBindingRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\WithScheduleTimingRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\StrFactoryResetRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\JsFromUnicodeRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\PasswordResetSubjectRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\PaginationDefaultViewRule',
            'MuhammadSadeeq\\LaravelUpgradesRector\\PHPStan\\Rules\\ArrayFirstLastPolyfillRule',
        ];

        self::assertSame($expected, $classes);

        foreach ($classes as $class) {
            self::assertTrue(class_exists($class), $class.' is registered but does not exist.');

            $reflection = new \ReflectionClass($class);
            self::assertTrue(
                $reflection->implementsInterface(Rule::class) || $reflection->isSubclassOf(Rule::class),
                $class.' is not a PHPStan rule.'
            );
        }

        foreach ([
            'ColumnChangeRequiresModifiersRule',
            'HasUuidsNowV7Rule',
            'BlueprintConstructorConnectionRule',
            'DatabaseTokenRepositoryExpiresRule',
            'SchemaInspectionAllSchemasRule',
            'LocalDiskDefaultRootRule',
            'DomainRoutePrecedenceRule',
            'SessionSerializationRule',
        ] as $class) {
            self::assertStringNotContainsString('Rules\\'.$class, $contents);
        }
    }
}
