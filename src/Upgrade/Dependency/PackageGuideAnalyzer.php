<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency;

use Composer\Semver\VersionParser;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\FindingCollector;

/**
 * Turns dependency major crossings into report-native, data-driven findings.
 * A package's manifest constraint cannot establish its installed major, so
 * this analyzer intentionally requires a parseable lock/installed version.
 */
final class PackageGuideAnalyzer
{
    public function __construct(private readonly PackageGuideCatalog $catalog) {}

    /**
     * @param  list<DependencyDecision>  $decisions
     */
    public function analyze(array $decisions, int $laravelMajor, string $workingDirectory): PackageGuideAnalysis
    {
        $collector = new FindingCollector;
        $summaries = [];

        foreach ($decisions as $decision) {
            if ($decision->action !== DependencyDecision::ACTION_BUMP || $decision->installed === null || $decision->proposed === null) {
                continue;
            }

            $fromMajor = self::versionMajor($decision->installed);
            $toMajor = self::constraintMajor($decision->proposed);

            if ($fromMajor === null || $toMajor === null || $toMajor <= $fromMajor) {
                continue;
            }

            $packageGuide = $this->catalog->forPackage($decision->package);

            if ($packageGuide === null) {
                continue;
            }

            for ($guideMajor = $fromMajor + 1; $guideMajor <= $toMajor; $guideMajor++) {
                $majorGuide = $packageGuide->forMajor($guideMajor);

                if ($majorGuide === null) {
                    continue;
                }

                $componentCount = $majorGuide->counter?->count($workingDirectory);
                $suffix = $componentCount === null
                    ? ''
                    : sprintf(' Detected %d %s.', $componentCount, $majorGuide->counter?->label);
                $futureNote = $majorGuide->isFuture() && $majorGuide->notes !== null
                    ? ' '.$majorGuide->notes
                    : '';

                foreach ($majorGuide->items as $item) {
                    $guideUrl = $item->guideUrl ?? $majorGuide->guideUrl;
                    $collector->add(
                        ruleId: self::ruleId($decision->package, $guideMajor, $item->id),
                        severity: $item->severity,
                        laravelVersion: $laravelMajor,
                        file: $decision->installedSource === 'installed' ? 'vendor/composer/installed.json' : 'composer.lock',
                        line: 0,
                        message: $item->message.$suffix.$futureNote,
                        action: $item->action.$futureNote,
                        guideUrl: $guideUrl,
                        confidence: 'high',
                    );
                }

                $summaries[] = [
                    'package' => $decision->package,
                    'section' => $decision->section,
                    'installed' => $decision->installed,
                    'fromMajor' => $fromMajor,
                    'toMajor' => $toMajor,
                    'guideMajor' => $guideMajor,
                    'guideUrl' => $majorGuide->guideUrl,
                    'items' => count($majorGuide->items),
                    'componentCount' => $componentCount,
                    'componentLabel' => $majorGuide->counter?->label,
                    'status' => $majorGuide->status,
                    'notes' => $majorGuide->notes,
                    'messages' => array_map(static fn (PackageGuideItem $item): string => $item->message, $majorGuide->items),
                    'actions' => array_map(static fn (PackageGuideItem $item): string => $item->action, $majorGuide->items),
                    'installedSource' => $decision->installedSource,
                ];
            }
        }

        return new PackageGuideAnalysis($collector->all(), $summaries);
    }

    public static function versionMajor(string $version): ?int
    {
        return self::normalizedMajor($version, false);
    }

    public static function constraintMajor(string $constraint): ?int
    {
        return self::normalizedMajor($constraint, true);
    }

    private static function normalizedMajor(string $value, bool $constraint): ?int
    {
        $value = trim($value);

        if ($constraint && str_starts_with($value, '^')) {
            $value = trim(substr($value, 1));
        }

        // A single Composer version/tag is representable in a guide. Ranges,
        // aliases, and branch constraints are intentionally not inferred.
        if ($value === '' || preg_match('/^[^,\s|]+$/', $value) !== 1) {
            return null;
        }

        try {
            if (VersionParser::parseStability($value) === 'dev') {
                return null;
            }

            $normalized = (new VersionParser)->normalize($value);

            if (VersionParser::parseStability($normalized) === 'dev') {
                return null;
            }
        } catch (\UnexpectedValueException) {
            return null;
        }

        if (preg_match('/^([0-9]+)\./', $normalized, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private static function ruleId(string $package, int $major, string $item): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '.', $package);
        $slug = is_string($slug) ? trim($slug, '.') : 'package';

        return sprintf('laravelUpgrade.packageGuide.%s.%d.%s', $slug, $major, $item);
    }
}
