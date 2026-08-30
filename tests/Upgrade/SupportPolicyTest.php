<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade;

use Composer\Semver\Semver;
use InvalidArgumentException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Advisory\PhpStanConfigGenerator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Orchestrator\UpgradePlan;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\SupportPolicy;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\SupportPolicyLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @phpstan-type PolicyDocument array{
 *     '$schema': string,
 *     schemaVersion: int,
 *     maxPathCount: int,
 *     paths: list<array{source: int, target: int}>,
 *     sources: array<int, array{phpMinimum: string, securityFixUntil: string}>
 * }
 */
final class SupportPolicyTest extends TestCase
{
    public function test_canonical_policy_exposes_the_three_adjacent_paths_and_source_facts(): void
    {
        $policy = SupportPolicy::default();

        self::assertSame(1, $policy->version());
        self::assertSame(3, $policy->maxPathCount());
        self::assertSame([10, 11, 12], $policy->sourceMajors());
        self::assertSame([11, 12, 13], $policy->targetMajors());
        self::assertSame('8.1.0', $policy->minimumPhpVersion());
        self::assertSame('^8.1.0', $policy->packagePhpConstraint());
        self::assertTrue(Semver::satisfies('8.1.0', $policy->packagePhpConstraint()));
        self::assertFalse(Semver::satisfies('8.0.99', $policy->packagePhpConstraint()));
        self::assertSame('2025-02-04', $policy->oldestSourceSecurityFixUntil());
        self::assertSame([10, 11, 12, 13], $policy->supportedMajors());
    }

    public function test_package_composer_floor_matches_the_oldest_source_floor(): void
    {
        /** @var array{require: array{php: string}} $composer */
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $constraint = $composer['require']['php'] ?? null;

        self::assertSame('^8.1', $constraint);
        self::assertTrue(Semver::satisfies(SupportPolicy::default()->minimumPhpVersion(), $constraint));
        self::assertFalse(Semver::satisfies('8.0.99', $constraint));
    }

    public function test_loader_rejects_invalid_json(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'support-policy-');

        if ($path === false) {
            self::fail('Could not create a temporary policy file.');
        }

        try {
            file_put_contents($path, '{not-json');
            $this->expectException(RuntimeException::class);
            SupportPolicyLoader::load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_loader_rejects_non_object_documents(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'support-policy-');

        if ($path === false) {
            self::fail('Could not create a temporary policy file.');
        }

        try {
            file_put_contents($path, '[]');
            $this->expectException(InvalidArgumentException::class);
            SupportPolicyLoader::load($path);
        } finally {
            unlink($path);
        }
    }

    public function test_invalid_schema_shapes_dates_versions_and_paths_are_rejected(): void
    {
        $this->assertInvalidPolicy('schema', [self::class, 'withInvalidSchema']);
        $this->assertInvalidPolicy('version', [self::class, 'withInvalidVersion']);
        $this->assertInvalidPolicy('date', [self::class, 'withInvalidDate']);
        $this->assertInvalidPolicy('php', [self::class, 'withInvalidPhp']);
        $this->assertInvalidPolicy('path', [self::class, 'withInvalidPath']);
    }

    public function test_maximum_three_and_contiguous_order_are_enforced(): void
    {
        $document = $this->canonicalDocument();
        $document['paths'][] = ['source' => 13, 'target' => 14];
        $document['sources'][13] = ['phpMinimum' => '8.3.0', 'securityFixUntil' => '2028-02-01'];

        $this->expectException(InvalidArgumentException::class);
        SupportPolicy::fromArray($document);
    }

    public function test_retirement_requires_window_pressure_replacement_and_security_eol(): void
    {
        $policy = SupportPolicy::default();

        self::assertFalse($policy->canRetireOldest(13, 14, '2025-02-04'));
        self::assertTrue($policy->canRetireOldest(13, 14, '2025-02-05'));
        self::assertFalse($policy->canRetireOldest(12, 13, '2025-02-05'));
        self::assertFalse($policy->canRetireOldest(13, 15, '2025-02-05'));
    }

    public function test_three_paths_are_not_dropped_just_because_oldest_is_eol(): void
    {
        $policy = SupportPolicy::default();

        self::assertSame([10, 11, 12], $policy->sourceMajors());
        self::assertSame('2025-02-04', $policy->oldestSourceSecurityFixUntil());
        self::assertFalse($policy->canRetireOldest(12, 13, '2025-02-05'));
    }

    public function test_custom_policy_drives_plan_and_advisory_target_validation(): void
    {
        $document = $this->canonicalDocument();
        array_shift($document['paths']);
        unset($document['sources'][10]);
        $document['paths'][] = ['source' => 13, 'target' => 14];
        $document['sources'][13] = ['phpMinimum' => '8.3.0', 'securityFixUntil' => '2028-02-01'];
        $policy = SupportPolicy::fromArray($document);

        self::assertSame([12, 13, 14], (new UpgradePlan(11, 14, false, null, [], $policy))->transitions());

        $this->expectException(RuntimeException::class);
        (new PhpStanConfigGenerator(dirname(__DIR__, 2), $policy))->generate(
            sys_get_temp_dir(),
            11,
            sys_get_temp_dir().'/support-policy-'.uniqid('', true),
        );
    }

    public function test_package_composer_constraint_preserves_a_nonzero_patch_floor(): void
    {
        $document = $this->canonicalDocument();
        $document['sources'][10]['phpMinimum'] = '8.1.4';
        $policy = SupportPolicy::fromArray($document);
        $constraint = $policy->packagePhpConstraint();

        self::assertSame('^8.1.4', $constraint);
        self::assertTrue(Semver::satisfies('8.1.4', $constraint));
        self::assertFalse(Semver::satisfies('8.1.3', $constraint));
    }

    public function test_policy_dates_are_normalized_to_utc(): void
    {
        self::assertSame('UTC', SupportPolicy::default()->securityFixUntilDate(10)->getTimezone()->getName());
    }

    /** @return PolicyDocument */
    private function canonicalDocument(): array
    {
        return [
            '$schema' => SupportPolicy::SCHEMA,
            'schemaVersion' => 1,
            'maxPathCount' => 3,
            'paths' => [
                ['source' => 10, 'target' => 11],
                ['source' => 11, 'target' => 12],
                ['source' => 12, 'target' => 13],
            ],
            'sources' => [
                10 => ['phpMinimum' => '8.1.0', 'securityFixUntil' => '2025-02-04'],
                11 => ['phpMinimum' => '8.2.0', 'securityFixUntil' => '2026-03-12'],
                12 => ['phpMinimum' => '8.2.0', 'securityFixUntil' => '2027-02-24'],
            ],
        ];
    }

    /**
     * @param  callable(PolicyDocument): array<string, mixed>  $mutate
     */
    private function assertInvalidPolicy(string $label, callable $mutate): void
    {
        try {
            SupportPolicy::fromArray($mutate($this->canonicalDocument()));
            self::fail(sprintf('Malformed %s policy should fail.', $label));
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @param  PolicyDocument  $document
     * @return array<string, mixed>
     */
    private static function withInvalidSchema(array $document): array
    {
        $document['$schema'] = 'other';

        return $document;
    }

    /**
     * @param  PolicyDocument  $document
     * @return array<string, mixed>
     */
    private static function withInvalidVersion(array $document): array
    {
        $document['schemaVersion'] = 2;

        return $document;
    }

    /**
     * @param  PolicyDocument  $document
     * @return array<string, mixed>
     */
    private static function withInvalidDate(array $document): array
    {
        $document['sources'][10]['securityFixUntil'] = '2025-02-31';

        return $document;
    }

    /**
     * @param  PolicyDocument  $document
     * @return array<string, mixed>
     */
    private static function withInvalidPhp(array $document): array
    {
        $document['sources'][10]['phpMinimum'] = '8.1';

        return $document;
    }

    /**
     * @param  PolicyDocument  $document
     * @return array<string, mixed>
     */
    private static function withInvalidPath(array $document): array
    {
        $document['paths'][1]['target'] = 14;

        return $document;
    }
}
