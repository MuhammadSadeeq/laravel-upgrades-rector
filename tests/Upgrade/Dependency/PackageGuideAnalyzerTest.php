<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\DependencyDecision;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideAnalyzer;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideCatalog;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackageGuideSchemaValidator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Report\Finding;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PackageGuideAnalyzerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/package-guides-'.bin2hex(random_bytes(5));
        mkdir($this->directory, 0777, true);
        self::assertTrue(copy(
            dirname(__DIR__, 3).'/resources/compat/package-guides.schema.json',
            $this->directory.'/package-guides.schema.json',
        ));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function test_shipped_catalog_covers_the_requested_ecosystem_sets(): void
    {
        $catalog = new PackageGuideCatalog(dirname(__DIR__, 3).'/resources/compat/package-guides.json');

        foreach ([
            'laravel/cashier' => [15, 16],
            'laravel/passport' => [12, 13],
            'laravel/sanctum' => [4],
            'laravel/telescope' => [5],
            'laravel/horizon' => [5, 6],
            'livewire/livewire' => [3],
            'inertiajs/inertia-laravel' => [2],
            'laravel/jetstream' => [5],
            'spatie/laravel-permission' => [6],
            'spatie/laravel-medialibrary' => [11],
            'spatie/laravel-backup' => [9, 10],
            'spatie/laravel-data' => [4],
            'spatie/laravel-ignition' => [2],
        ] as $package => $majors) {
            $guide = $catalog->forPackage($package);
            self::assertNotNull($guide, $package);

            foreach ($majors as $major) {
                $majorGuide = $guide->forMajor($major);
                self::assertNotNull($majorGuide, $package.' '.$major);
                self::assertNotEmpty($majorGuide->items);
            }
        }

        self::assertTrue($catalog->forPackage('laravel/cashier')?->forMajor(16)?->isFuture());
        self::assertTrue($catalog->forPackage('laravel/horizon')?->forMajor(6)?->isFuture());
    }

    public function test_shipped_data_is_validated_against_its_schema(): void
    {
        $dataPath = dirname(__DIR__, 3).'/resources/compat/package-guides.json';
        $schemaPath = dirname(__DIR__, 3).'/resources/compat/package-guides.schema.json';

        (new PackageGuideSchemaValidator)->validate($dataPath, $schemaPath);
        self::assertFileExists($dataPath);
    }

    public function test_catalog_fails_closed_when_sibling_schema_is_missing_or_unreadable(): void
    {
        $withoutSchema = $this->directory.'/without-schema';
        mkdir($withoutSchema, 0777, true);
        $dataPath = $withoutSchema.'/package-guides.json';
        self::assertNotFalse(file_put_contents($dataPath, '{}'));
        self::assertFileDoesNotExist($withoutSchema.'/package-guides.schema.json');

        try {
            (new PackageGuideCatalog($dataPath))->all();
            self::fail('A missing sibling schema must fail closed.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Could not read package guide schema resource', $exception->getMessage());
        }

        $unreadableSchema = $this->directory.'/schema-directory';
        mkdir($unreadableSchema, 0777, true);
        mkdir($unreadableSchema.'/package-guides.schema.json', 0777, true);
        $unreadableDataPath = $unreadableSchema.'/package-guides.json';
        self::assertNotFalse(file_put_contents($unreadableDataPath, '{}'));

        try {
            (new PackageGuideCatalog($unreadableDataPath))->all();
            self::fail('An unreadable sibling schema must fail closed.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Could not read package guide schema resource', $exception->getMessage());
        }
    }

    public function test_schema_validation_reaches_nested_package_major_item_and_counter_objects(): void
    {
        $dataPath = dirname(__DIR__, 3).'/resources/compat/package-guides.json';
        $schemaPath = dirname(__DIR__, 3).'/resources/compat/package-guides.schema.json';
        $raw = file_get_contents($dataPath);
        self::assertIsString($raw);

        /** @var array<string, mixed> $document */
        $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $packages = $document['packages'] ?? null;
        self::assertIsArray($packages);
        $livewire = $packages['livewire/livewire'] ?? null;
        self::assertIsArray($livewire);
        $majors = $livewire['majors'] ?? null;
        self::assertIsArray($majors);
        $major = $majors['3'] ?? null;
        self::assertIsArray($major);
        $items = $major['items'] ?? null;
        self::assertIsArray($items);
        $item = $items[0] ?? null;
        self::assertIsArray($item);
        $counter = $major['counter'] ?? null;
        self::assertIsArray($counter);

        $mutations = [
            'package' => static function (array &$value) use ($packages, $livewire): void {
                $package = $livewire;
                $package['guideUrl'] = 123;
                $value['packages'] = $packages;
                $value['packages']['livewire/livewire'] = $package;
            },
            'major' => static function (array &$value) use ($packages, $livewire, $majors, $major): void {
                $major['items'] = 'not-a-list';
                $entry = $livewire;
                $entry['majors'] = $majors;
                $entry['majors']['3'] = $major;
                $value['packages'] = $packages;
                $value['packages']['livewire/livewire'] = $entry;
            },
            'item' => static function (array &$value) use ($packages, $livewire, $majors, $major, $items, $item): void {
                $item['severity'] = 'critical';
                $major['items'] = $items;
                $major['items'][0] = $item;
                $entry = $livewire;
                $entry['majors'] = $majors;
                $entry['majors']['3'] = $major;
                $value['packages'] = $packages;
                $value['packages']['livewire/livewire'] = $entry;
            },
            'counter' => static function (array &$value) use ($packages, $livewire, $majors, $major, $counter): void {
                $counter['extensions'] = ['.'];
                $major['counter'] = $counter;
                $entry = $livewire;
                $entry['majors'] = $majors;
                $entry['majors']['3'] = $major;
                $value['packages'] = $packages;
                $value['packages']['livewire/livewire'] = $entry;
            },
        ];

        foreach ($mutations as $label => $mutation) {
            $mutatedPath = $this->directory.'/schema-'.$label.'.json';
            $mutated = $document;
            $mutation($mutated);
            self::assertNotFalse(file_put_contents($mutatedPath, json_encode($mutated, JSON_THROW_ON_ERROR)));

            try {
                (new PackageGuideSchemaValidator)->validate($mutatedPath, $schemaPath);
                self::fail(sprintf('Malformed %s data must fail schema validation.', $label));
            } catch (RuntimeException $exception) {
                self::assertStringStartsWith('Package guide schema validation failed', $exception->getMessage());
            }
        }
    }

    public function test_schema_requires_notes_for_future_major_metadata(): void
    {
        $dataPath = dirname(__DIR__, 3).'/resources/compat/package-guides.json';
        $schemaPath = dirname(__DIR__, 3).'/resources/compat/package-guides.schema.json';
        $raw = file_get_contents($dataPath);
        self::assertIsString($raw);

        /** @var array<string, mixed> $document */
        $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $packages = $document['packages'] ?? null;
        self::assertIsArray($packages);
        $cashier = $packages['laravel/cashier'] ?? null;
        self::assertIsArray($cashier);
        $majors = $cashier['majors'] ?? null;
        self::assertIsArray($majors);
        $future = $majors['16'] ?? null;
        self::assertIsArray($future);
        unset($future['notes']);
        $majors['16'] = $future;
        $cashier['majors'] = $majors;
        $packages['laravel/cashier'] = $cashier;
        $document['packages'] = $packages;

        $mutatedPath = $this->directory.'/schema-future-without-notes.json';
        self::assertNotFalse(file_put_contents($mutatedPath, json_encode($document, JSON_THROW_ON_ERROR)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is missing required property "notes"');
        (new PackageGuideSchemaValidator)->validate($mutatedPath, $schemaPath);
    }

    public function test_version_major_accepts_composer_release_variants_but_not_dev_versions(): void
    {
        self::assertSame(3, PackageGuideAnalyzer::versionMajor('v3.0.0-beta.1'));
        self::assertSame(3, PackageGuideAnalyzer::versionMajor('3.0.0+meta'));
        self::assertSame(3, PackageGuideAnalyzer::versionMajor('3.0.0-RC1'));
        self::assertSame(3, PackageGuideAnalyzer::constraintMajor('^v3.0.0-RC1'));
        self::assertSame(0, PackageGuideAnalyzer::versionMajor('v0.9.0-beta.1'));
        self::assertNull(PackageGuideAnalyzer::versionMajor('dev-main'));
        self::assertNull(PackageGuideAnalyzer::versionMajor('3.x-dev'));
        self::assertNull(PackageGuideAnalyzer::constraintMajor('>=3.0'));
    }

    public function test_future_major_guidance_is_explicitly_marked_manual(): void
    {
        $analysis = $this->analyzer()->analyze([
            $this->decision('laravel/cashier', 'v15.0.0', '^16.0'),
        ], 12, $this->directory);

        self::assertSame('future', $analysis->guides[0]['status']);
        self::assertStringContainsString('Manual guidance only', $analysis->findings[0]->message);
        self::assertStringContainsString('Manual guidance only', $analysis->findings[0]->action);
    }

    public function test_major_crossings_emit_actionable_findings_and_livewire_component_count(): void
    {
        mkdir($this->directory.'/app/Livewire/Nested', 0777, true);
        mkdir($this->directory.'/resources/views/livewire', 0777, true);
        file_put_contents($this->directory.'/app/Livewire/One.php', '<?php');
        file_put_contents($this->directory.'/app/Livewire/Nested/Two.php', '<?php');
        file_put_contents($this->directory.'/resources/views/livewire/three.blade.php', '<div />');
        file_put_contents($this->directory.'/resources/views/livewire/readme.txt', 'not a component');

        $analysis = $this->analyzer()->analyze([
            $this->decision('livewire/livewire', 'v2.12.0', '^3.0'),
            $this->decision('laravel/passport', 'v11.0.0', '^13.0'),
            $this->decision('laravel/sanctum', 'v3.0.0', '^4.0'),
            $this->decision('spatie/laravel-backup', 'v8.0.0', '^10.0'),
        ], 13, $this->directory);

        $livewire = array_values(array_filter(
            $analysis->findings,
            static fn (Finding $finding): bool => str_starts_with($finding->ruleId, 'laravelUpgrade.packageGuide.livewire.livewire.3.'),
        ));
        self::assertCount(2, $livewire);
        self::assertStringContainsString('Detected 3 Livewire component files.', $livewire[0]->message);
        self::assertSame('https://livewire.laravel.com/docs/upgrading', $livewire[0]->guideUrl);

        $passportGuides = array_values(array_filter(
            $analysis->guides,
            static fn (array $guide): bool => $guide['package'] === 'laravel/passport',
        ));
        self::assertSame([12, 13], array_column($passportGuides, 'guideMajor'));

        $backupGuides = array_values(array_filter(
            $analysis->guides,
            static fn (array $guide): bool => $guide['package'] === 'spatie/laravel-backup',
        ));
        self::assertSame([9, 10], array_column($backupGuides, 'guideMajor'));
        self::assertNotEmpty($analysis->findings);
    }

    public function test_require_dev_crossing_keeps_section_and_unknown_versions_are_safe(): void
    {
        $decision = new DependencyDecision(
            'livewire/livewire',
            'require-dev',
            '^2.0',
            '^3.0',
            DependencyDecision::ACTION_BUMP,
            'requires 3.0.1',
            'v2.12.0',
        );
        $unknownVersion = $this->decision('livewire/livewire', 'dev-main', '^3.0');
        $alreadyCurrent = $this->decision('livewire/livewire', 'v3.0.0', '^3.0');
        $alreadyCurrent = new DependencyDecision(
            $alreadyCurrent->package,
            $alreadyCurrent->section,
            $alreadyCurrent->current,
            $alreadyCurrent->proposed,
            DependencyDecision::ACTION_KEEP,
            $alreadyCurrent->reason,
            $alreadyCurrent->installed,
        );

        $analysis = $this->analyzer()->analyze([$decision, $unknownVersion, $alreadyCurrent], 11, $this->directory);

        self::assertNotEmpty($analysis->findings);
        $sections = array_column($analysis->guides, 'section');
        $sections = array_values(array_filter($sections, 'is_string'));
        self::assertSame(['require-dev'], array_values(array_unique($sections)));
        self::assertSame(1, count($analysis->guides));
        self::assertSame('dev-main', $unknownVersion->installed);
    }

    public function test_missing_lock_version_does_not_infer_a_false_package_crossing(): void
    {
        $decision = new DependencyDecision(
            'laravel/passport',
            'require',
            '^11.0',
            '^13.0',
            DependencyDecision::ACTION_BUMP,
            'requires 13.7.6',
        );

        $analysis = $this->analyzer()->analyze([$decision], 13, $this->directory);

        self::assertSame([], $analysis->findings);
        self::assertSame([], $analysis->guides);
    }

    public function test_installed_metadata_provenance_is_reflected_in_findings(): void
    {
        $decision = new DependencyDecision(
            'livewire/livewire',
            'require',
            '^2.0',
            '^3.0',
            DependencyDecision::ACTION_BUMP,
            'requires target major',
            'v2.12.0',
            'installed',
        );

        $analysis = $this->analyzer()->analyze([$decision], 11, $this->directory);

        self::assertNotEmpty($analysis->findings);
        self::assertSame('vendor/composer/installed.json', $analysis->findings[0]->file);
        self::assertSame('installed', $analysis->guides[0]['installedSource']);
    }

    public function test_malformed_data_fails_closed_before_analysis(): void
    {
        $path = $this->directory.'/malformed.json';
        file_put_contents($path, json_encode([
            '$schema' => './package-guides.schema.json',
            'schemaVersion' => 1,
            'packages' => [
                'laravel/passport' => [
                    'guideUrl' => 'javascript:alert(1)',
                    'majors' => ['13' => ['items' => [['id' => 'x']]]],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Package guide schema validation failed');
        (new PackageGuideCatalog($path))->all();
    }

    public function test_urls_are_externally_configurable_per_major_and_item(): void
    {
        $path = $this->directory.'/custom.json';
        file_put_contents($path, json_encode([
            '$schema' => './package-guides.schema.json',
            'schemaVersion' => 1,
            'packages' => [
                'acme/widget' => [
                    'guideUrl' => 'https://example.test/widget',
                    'majors' => [
                        '2' => [
                            'guideUrl' => 'https://example.test/widget/2',
                            'items' => [[
                                'id' => 'api',
                                'severity' => 'info',
                                'message' => 'Widget API changed.',
                                'action' => 'Review the widget API.',
                                'guideUrl' => 'https://example.test/widget/2/api',
                            ]],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $decision = $this->decision('acme/widget', 'v1.0.0', '^2.0');
        $analysis = (new PackageGuideAnalyzer(new PackageGuideCatalog($path)))->analyze([$decision], 12, $this->directory);

        self::assertCount(1, $analysis->findings);
        self::assertSame('https://example.test/widget/2/api', $analysis->findings[0]->guideUrl);
    }

    public function test_counter_schema_rejects_empty_normalized_extensions_and_unsafe_paths(): void
    {
        foreach ([
            ['extensions' => ['.'], 'paths' => ['app']],
            ['extensions' => ['php'], 'paths' => ['.']],
            ['extensions' => ['php'], 'paths' => ['../app']],
            ['extensions' => ['php'], 'paths' => ['app\\Livewire']],
            ['extensions' => ['php'], 'paths' => ['C:/app']],
            ['extensions' => ['php'], 'paths' => [' C:/app ']],
            ['extensions' => ['php'], 'paths' => ['C:\\app']],
            ['extensions' => ['php'], 'paths' => ['\\\\server\\share']],
        ] as $counter) {
            $path = $this->directory.'/invalid-counter-'.bin2hex(random_bytes(3)).'.json';
            file_put_contents($path, json_encode([
                '$schema' => './package-guides.schema.json',
                'schemaVersion' => 1,
                'packages' => [
                    'acme/widget' => [
                        'guideUrl' => 'https://example.test/widget',
                        'majors' => [
                            '2' => [
                                'items' => [[
                                    'id' => 'api',
                                    'severity' => 'info',
                                    'message' => 'Widget API changed.',
                                    'action' => 'Review the widget API.',
                                ]],
                                'counter' => [
                                    'label' => 'files',
                                    'paths' => $counter['paths'],
                                    'extensions' => $counter['extensions'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            try {
                (new PackageGuideCatalog($path))->all();
                self::fail('Malformed counter data must be rejected.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Package guide schema validation failed', $exception->getMessage());
            } finally {
                self::assertFileExists($path);
            }
        }
    }

    private function analyzer(): PackageGuideAnalyzer
    {
        return new PackageGuideAnalyzer(new PackageGuideCatalog(dirname(__DIR__, 3).'/resources/compat/package-guides.json'));
    }

    private function decision(string $package, string $installed, string $proposed): DependencyDecision
    {
        return new DependencyDecision(
            $package,
            'require',
            '^'.PackageGuideAnalyzer::versionMajor($installed).'.0',
            $proposed,
            DependencyDecision::ACTION_BUMP,
            'requires target major',
            $installed,
        );
    }

    private function removeDirectory(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
