<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrix;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\ConstraintPlanner;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\DependencyDecision;
use PHPUnit\Framework\TestCase;

final class ConstraintPlannerTest extends TestCase
{
    private ConstraintPlanner $planner;

    protected function setUp(): void
    {
        $packageJsonPath = dirname(__DIR__, 3).'/resources/compat/packages.json';
        $removalsJsonPath = dirname(__DIR__, 3).'/resources/compat/removals.json';

        $this->planner = new ConstraintPlanner(
            new CompatibilityMatrix($packageJsonPath),
            $removalsJsonPath
        );
    }

    public function test_framework_below_target_is_bumped(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^10.10', 11);

        self::assertSame(DependencyDecision::ACTION_BUMP, $decision->action);
        self::assertSame('^11.0.0', $decision->proposed);
    }

    public function test_framework_already_on_target_is_kept(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^11.31', 11);

        self::assertSame(DependencyDecision::ACTION_KEEP, $decision->action);
        self::assertStringContainsString('already compatible', $decision->reason);
    }

    public function test_constraint_admitting_target_minimum_is_not_flattened(): void
    {
        // The old regex-based updater destroyed constraints like "^10.0 || ^11.0";
        // the semver planner keeps any constraint admitting versions for the target.
        $decision = $this->decisionFor('livewire/livewire', '^3.0.1', 11);

        self::assertSame(DependencyDecision::ACTION_KEEP, $decision->action);
    }

    public function test_constraint_stricter_than_the_matrix_minimum_is_never_lowered(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^11.31', 11);

        self::assertSame(DependencyDecision::ACTION_KEEP, $decision->action);
    }

    public function test_constraint_below_target_is_bumped_even_when_disjunct(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^10.0 || ^10.2', 11);

        self::assertSame(DependencyDecision::ACTION_BUMP, $decision->action);
        self::assertSame('^11.0.0', $decision->proposed);
    }

    public function test_php_floor_below_target_is_bumped_even_though_it_admits_the_floor(): void
    {
        // ^8.1 admits 8.2.0 but also allows running on 8.1, where Laravel 11 breaks.
        $decision = $this->decisionFor('php', '^8.1', 11);

        self::assertSame(DependencyDecision::ACTION_BUMP, $decision->action);
        self::assertSame('^8.2.0', $decision->proposed);
    }

    public function test_php_floor_at_or_above_target_is_kept(): void
    {
        self::assertSame(DependencyDecision::ACTION_KEEP, $this->decisionFor('php', '^8.2', 11)->action);
        self::assertSame(DependencyDecision::ACTION_KEEP, $this->decisionFor('php', '>=8.3', 13)->action);
    }

    public function test_unknown_package_is_flagged_instead_of_guessed(): void
    {
        $decision = $this->decisionFor('acme/unheard-of-package', '^1.0', 11);

        self::assertSame(DependencyDecision::ACTION_UNKNOWN, $decision->action);
        self::assertNull($decision->proposed);
    }

    public function test_require_dev_package_is_bumped_in_section(): void
    {
        $manifest = [
            'require' => ['laravel/framework' => '^10.10'],
            'require-dev' => ['pestphp/pest' => '^1.22'],
        ];

        $byName = $this->byName($this->planner->planAll(12, $manifest, []));
        $pest = $byName['pestphp/pest'];

        self::assertSame(DependencyDecision::ACTION_BUMP, $pest->action);
        self::assertSame('require-dev', $pest->section);
        self::assertSame('^3.0.0', $pest->proposed);
    }

    public function test_removal_only_when_no_locked_package_requires_it(): void
    {
        $manifest = ['require' => ['doctrine/dbal' => '^3.6']];
        $lockedWithoutDependent = [
            'doctrine/orm' => ['name' => 'doctrine/orm', 'version' => '2.15.0'],
        ];
        $lockedWithDependent = [
            'acme/legacy' => [
                'name' => 'acme/legacy',
                'version' => '1.0.0',
                'require' => ['doctrine/dbal' => '^3.0'],
            ],
        ];

        $free = $this->byName($this->planner->planAll(11, $manifest, $lockedWithoutDependent));
        $used = $this->byName($this->planner->planAll(11, $manifest, $lockedWithDependent));

        self::assertSame(DependencyDecision::ACTION_REMOVE, $free['doctrine/dbal']->action);
        self::assertSame(DependencyDecision::ACTION_KEEP, $used['doctrine/dbal']->action);
        self::assertStringContainsString('acme/legacy', $used['doctrine/dbal']->reason);
    }

    public function test_removal_skips_transitive_dependencies(): void
    {
        $manifest = ['require' => ['laravel/framework' => '^10.10']];

        foreach ($this->planner->planAll(11, $manifest, []) as $decision) {
            self::assertNotSame(DependencyDecision::ACTION_REMOVE, $decision->action);
        }
    }

    /**
     * @param  list<DependencyDecision>  $decisions
     * @return array<string, DependencyDecision>
     */
    private function byName(array $decisions): array
    {
        $map = [];

        foreach ($decisions as $decision) {
            $map[$decision->package] = $decision;
        }

        return $map;
    }

    private function decisionFor(string $package, string $constraint, int $targetMajor): DependencyDecision
    {
        $manifest = ['require' => [$package => $constraint]];

        foreach ($this->planner->planAll($targetMajor, $manifest, []) as $decision) {
            if ($decision->package === $package) {
                return $decision;
            }
        }

        self::fail(sprintf('No decision was produced for "%s".', $package));
    }
}
