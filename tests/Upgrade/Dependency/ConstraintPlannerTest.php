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
        $packageJsonPath = dirname(__DIR__, 3) . '/resources/compat/packages.json';
        $removalsJsonPath = dirname(__DIR__, 3) . '/resources/compat/removals.json';

        $this->planner = new ConstraintPlanner(
            new CompatibilityMatrix($packageJsonPath),
            $removalsJsonPath
        );
    }

    public function testFrameworkBelowTargetIsBumped(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^10.10', 11);

        self::assertSame(DependencyDecision::ACTION_BUMP, $decision->action);
        self::assertSame('^11.0.0', $decision->proposed);
    }

    public function testFrameworkAlreadyOnTargetIsKept(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^11.31', 11);

        self::assertSame(DependencyDecision::ACTION_KEEP, $decision->action);
        self::assertStringContainsString('already compatible', $decision->reason);
    }

    public function testConstraintAdmittingTargetMinimumIsNotFlattened(): void
    {
        // The old regex-based updater destroyed constraints like "^10.0 || ^11.0";
        // the semver planner keeps any constraint admitting versions for the target.
        $decision = $this->decisionFor('livewire/livewire', '^3.0.1', 11);

        self::assertSame(DependencyDecision::ACTION_KEEP, $decision->action);
    }

    public function testConstraintStricterThanTheMatrixMinimumIsNeverLowered(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^11.31', 11);

        self::assertSame(DependencyDecision::ACTION_KEEP, $decision->action);
    }

    public function testConstraintBelowTargetIsBumpedEvenWhenDisjunct(): void
    {
        $decision = $this->decisionFor('laravel/framework', '^10.0 || ^10.2', 11);

        self::assertSame(DependencyDecision::ACTION_BUMP, $decision->action);
        self::assertSame('^11.0.0', $decision->proposed);
    }

    public function testPhpFloorBelowTargetIsBumpedEvenThoughItAdmitsTheFloor(): void
    {
        // ^8.1 admits 8.2.0 but also allows running on 8.1, where Laravel 11 breaks.
        $decision = $this->decisionFor('php', '^8.1', 11);

        self::assertSame(DependencyDecision::ACTION_BUMP, $decision->action);
        self::assertSame('^8.2.0', $decision->proposed);
    }

    public function testPhpFloorAtOrAboveTargetIsKept(): void
    {
        self::assertSame(DependencyDecision::ACTION_KEEP, $this->decisionFor('php', '^8.2', 11)->action);
        self::assertSame(DependencyDecision::ACTION_KEEP, $this->decisionFor('php', '>=8.3', 13)->action);
    }

    public function testUnknownPackageIsFlaggedInsteadOfGuessed(): void
    {
        $decision = $this->decisionFor('acme/unheard-of-package', '^1.0', 11);

        self::assertSame(DependencyDecision::ACTION_UNKNOWN, $decision->action);
        self::assertNull($decision->proposed);
    }

    public function testRequireDevPackageIsBumpedInSection(): void
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

    public function testRemovalOnlyWhenNoLockedPackageRequiresIt(): void
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

    public function testRemovalSkipsTransitiveDependencies(): void
    {
        $manifest = ['require' => ['laravel/framework' => '^10.10']];

        foreach ($this->planner->planAll(11, $manifest, []) as $decision) {
            self::assertNotSame(DependencyDecision::ACTION_REMOVE, $decision->action);
        }
    }

    /**
     * @param list<DependencyDecision> $decisions
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
