<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use MuhammadSadeeq\LaravelUpgradesRector\Support\Compat\CompatFileLoader;

/**
 * Decides, per direct dependency, whether the current constraint already
 * admits versions that support the target major, must be replaced, or can be
 * removed. All version comparisons run through composer/semver.
 *
 * Semantics:
 * - regular packages are kept when their constraint intersects with
 *   ">= matrix-minimum" (i.e. some installable version supports the target);
 *   anything else is bumped to `^<minimum>`;
 * - the `php` platform requirement additionally must not admit ANY version
 *   below the required floor (e.g. `^8.1` becomes `^8.2` even though `^8.1`
 *   admits 8.2), matching the upgrade guides;
 * - removals happen only when nothing else locked requires the package.
 */
final class ConstraintPlanner
{
    private const PLATFORM_PACKAGE = 'php';

    public function __construct(
        private readonly CompatibilityMatrix $matrix,
        private readonly string $removalsJsonPath,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest  decoded composer.json
     * @param  array<string, array<string, mixed>>  $lockedPackages  decoded composer.lock entries keyed by name
     * @return list<DependencyDecision>
     */
    public function planAll(int $targetMajor, array $manifest, array $lockedPackages): array
    {
        $decisions = $this->planRemovals($targetMajor, $manifest, $lockedPackages);

        $handledByRemovalPolicy = [];

        foreach ($decisions as $decision) {
            $handledByRemovalPolicy[$decision->package] = true;
        }

        return array_merge(
            $decisions,
            $this->plan($targetMajor, $manifest, $handledByRemovalPolicy)
        );
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, true>  $skipPackages
     * @return list<DependencyDecision>
     */
    public function plan(int $targetMajor, array $manifest, array $skipPackages = []): array
    {
        $decisions = [];

        foreach (['require', 'require-dev'] as $section) {
            $constraints = $manifest[$section] ?? null;

            if (! is_array($constraints)) {
                continue;
            }

            foreach ($constraints as $package => $constraint) {
                if (! is_string($package) || ! is_string($constraint)) {
                    continue;
                }

                if (isset($skipPackages[$package])) {
                    continue;
                }

                $decisions[] = $this->planPackage(
                    $targetMajor,
                    $this->matrix,
                    $package,
                    $section,
                    $constraint
                );
            }
        }

        return $decisions;
    }

    private function planPackage(
        int $targetMajor,
        CompatibilityMatrix $matrix,
        string $package,
        string $section,
        string $currentConstraint
    ): DependencyDecision {
        // The php platform requirement is compared against the matrix floor.
        $minimum = $matrix->minimumVersionFor($package, $targetMajor);

        if ($minimum === null) {
            return new DependencyDecision(
                $package,
                $section,
                $currentConstraint,
                null,
                DependencyDecision::ACTION_UNKNOWN,
                'no compatibility data — verify against the package\'s own documentation'
            );
        }

        if ($this->isCompatible($package, $minimum, $currentConstraint)) {
            return new DependencyDecision(
                $package,
                $section,
                $currentConstraint,
                null,
                DependencyDecision::ACTION_KEEP,
                sprintf('already compatible (%s admits %s)', $currentConstraint, $minimum)
            );
        }

        return new DependencyDecision(
            $package,
            $section,
            $currentConstraint,
            '^'.$minimum,
            DependencyDecision::ACTION_BUMP,
            sprintf('requires %s for Laravel %d', $minimum, $targetMajor)
        );
    }

    /**
     * Removals are only safe when no other locked package requires the package.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, array<string, mixed>>  $lockedPackages
     * @return list<DependencyDecision>
     */
    public function planRemovals(int $targetMajor, array $manifest, array $lockedPackages): array
    {
        $decisions = [];
        $removals = $this->removalsFor($targetMajor);

        foreach ($removals as $package) {
            $section = $this->directSection($manifest, $package);

            if ($section === null) {
                continue;
            }

            $requiredBy = $this->lockedRequirers($package, $lockedPackages);

            if ($requiredBy !== []) {
                $decisions[] = new DependencyDecision(
                    $package,
                    $section,
                    $this->currentConstraint($manifest, $package),
                    null,
                    DependencyDecision::ACTION_KEEP,
                    'kept — still required by '.implode(', ', $requiredBy)
                );

                continue;
            }

            $decisions[] = new DependencyDecision(
                $package,
                $section,
                $this->currentConstraint($manifest, $package),
                null,
                DependencyDecision::ACTION_REMOVE,
                'no longer needed for Laravel '.$targetMajor.' and unused by other packages'
            );
        }

        return $decisions;
    }

    private function isCompatible(string $package, string $minimum, string $currentConstraint): bool
    {
        $parser = new VersionParser;

        try {
            $parsed = $parser->parseConstraints($currentConstraint);
            // Both sides must go through VersionParser so that dev-precision
            // bounds are normalized consistently.
            $floor = $parser->parseConstraints('>='.$minimum);
        } catch (\UnexpectedValueException) {
            return false;
        }

        if ($package === self::PLATFORM_PACKAGE) {
            // Compatible only when nothing below the floor is admitted
            // (e.g. `^8.1` must be raised even though it admits 8.2).
            return Intervals::isSubsetOf($parsed, $floor);
        }

        // Compatible when at least one admitted version reaches the minimum;
        // constraints stricter than the matrix floor (e.g. `^11.31`) are kept.
        return Intervals::haveIntersections($parsed, $floor);
    }

    /**
     * @return list<string>
     */
    private function removalsFor(int $targetMajor): array
    {
        $data = CompatFileLoader::load($this->removalsJsonPath, 'removals');

        $forMajor = $data[(string) $targetMajor] ?? null;

        if (! is_array($forMajor)) {
            return [];
        }

        /** @var list<string> */
        return array_values(array_filter($forMajor, 'is_string'));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function directSection(array $manifest, string $package): ?string
    {
        foreach (['require', 'require-dev'] as $section) {
            $constraints = $manifest[$section] ?? null;

            if (is_array($constraints) && array_key_exists($package, $constraints)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function currentConstraint(array $manifest, string $package): ?string
    {
        foreach (['require', 'require-dev'] as $section) {
            $constraints = $manifest[$section] ?? null;

            if (is_array($constraints) && is_string($constraints[$package] ?? null)) {
                return $constraints[$package];
            }
        }

        return null;
    }

    /**
     * Locked packages (excluding the package itself) whose require section
     * mentions the given package.
     *
     * @param  array<string, array<string, mixed>>  $lockedPackages
     * @return list<string>
     */
    private function lockedRequirers(string $package, array $lockedPackages): array
    {
        $requirers = [];

        foreach ($lockedPackages as $name => $details) {
            if ($name === $package || ! is_array($details)) {
                continue;
            }

            $requires = $details['require'] ?? null;

            if (! is_array($requires)) {
                continue;
            }

            if (array_key_exists($package, $requires)) {
                $requirers[] = (string) $name;
            }
        }

        sort($requirers);

        return $requirers;
    }
}
