<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\Upgrade\Dependency;

use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrixArtifactValidator;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\CompatibilityMatrixBuilder;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\FixturePackagistTransport;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\Dependency\PackagistClient;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\HttpSourceException;
use MuhammadSadeeq\LaravelUpgradesRector\Upgrade\GuideDrift\HttpSourceFetcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CompatibilityMatrixBuilderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
    }

    public function test_refresh_filters_pre_releases_and_intersects_all_laravel_constraints(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0', '12' => '12.0.0', 'kind' => 'framework'],
                'php' => ['11' => '8.2.0', '12' => '8.2.0', 'kind' => 'platform'],
                'acme/widget' => ['11' => '0.1.0', '12' => '0.1.0', 'custom' => 'retained'],
            ],
        ]);
        $phpBody = json_encode(['generatedAt' => '2026-01-01', 'php' => [
            '11' => ['minimum' => '8.2.0'],
            '12' => ['minimum' => '8.2.0'],
        ]], JSON_THROW_ON_ERROR);
        file_put_contents($phpPath, $phpBody);

        $releases = [
            ['name' => 'acme/widget', 'version' => '1.0.0-beta', 'require' => ['illuminate/support' => '^11.0', 'illuminate/contracts' => '^11.0']],
            ['name' => 'acme/widget', 'version' => 'v1.0.0', 'version_normalized' => '1.0.0.0', 'require' => ['illuminate/support' => '^10.0']],
            ['name' => 'acme/widget', 'version' => '1.2.3', 'require' => ['illuminate/support' => '^11.0', 'illuminate/contracts' => '^11.0']],
            ['name' => 'acme/widget', 'version' => '2.0.0', 'require' => ['illuminate/support' => '^11.0', 'illuminate/contracts' => '^10.0']],
            ['name' => 'acme/widget', 'version' => '3.0.0', 'require' => ['illuminate/support' => '^11.0', 'illuminate/contracts' => '^11.0']],
            ['name' => 'acme/widget', 'version' => '3.5.0', 'require' => ['illuminate/support' => '^11.0 || ^12.0', 'illuminate/contracts' => '^12.0']],
            ['name' => 'acme/widget', 'version' => '4.0.0', 'require' => ['illuminate/support' => '^12.0', 'illuminate/contracts' => '^12.0']],
        ];
        $client = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['acme/widget' => $releases], 'next' => null], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);
        $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client);

        $candidate = $builder->build(new \DateTimeImmutable('2026-08-30'));
        self::assertIsArray($candidate['packages'] ?? null);
        self::assertIsArray($candidate['packages']['acme/widget'] ?? null);
        $widget = $candidate['packages']['acme/widget'];
        self::assertSame('1.2.3', $widget['11']);
        self::assertSame('3.5.0', $widget['12']);
        self::assertSame('retained', $widget['custom']);
        self::assertSame('2026-08-30', $candidate['generatedAt']);
    }

    public function test_constraints_intersect_the_supported_major_line_instead_of_only_major_zero(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0'],
                'php' => ['11' => '8.2.0'],
                'acme/widget' => ['11' => '0.1.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $client = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => '^11.0.8']],
            ]]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);

        $candidate = (new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client))->build(new \DateTimeImmutable('2026-08-30'));
        self::assertIsArray($candidate['packages'] ?? null);
        $packages = $candidate['packages'];
        self::assertIsArray($packages['acme/widget'] ?? null);
        self::assertSame('1.0.0', $packages['acme/widget']['11'] ?? null);
    }

    public function test_disjoint_requirements_fail_even_when_each_range_reaches_the_major(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0'],
                'php' => ['11' => '8.2.0'],
                'acme/widget' => ['11' => '0.1.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $client = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => '1.0.0', 'require' => [
                    'illuminate/support' => '>=11 <11.5',
                    'illuminate/contracts' => '>=11.5 <12',
                ]],
            ]]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);

        $this->expectException(RuntimeException::class);
        (new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client))->build();
    }

    public function test_overlapping_ranges_and_or_constraints_choose_the_lowest_compatible_release(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0', '12' => '12.0.0'],
                'php' => ['11' => '8.2.0', '12' => '8.2.0'],
                'acme/widget' => ['11' => '0.1.0'],
                'acme/or-widget' => ['11' => '0.1.0', '12' => '0.1.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => [
            '11' => ['minimum' => '8.2.0'],
            '12' => ['minimum' => '8.2.0'],
        ]], JSON_THROW_ON_ERROR));
        $client = new PackagistClient(static function (string $url): array {
            $package = str_contains($url, 'acme/or-widget') ? 'acme/or-widget' : 'acme/widget';
            $releases = $package === 'acme/widget'
                ? [[
                    'name' => $package,
                    'version' => '1.0.0',
                    'require' => [
                        'illuminate/support' => '>=11 <11.8',
                        'illuminate/contracts' => '>=11.5 <12',
                    ],
                ]]
                : [
                    ['name' => $package, 'version' => '1.0.0', 'require' => ['illuminate/support' => '^10.0']],
                    ['name' => $package, 'version' => '2.0.0', 'require' => ['illuminate/support' => '^10.0 || ^11.7']],
                    ['name' => $package, 'version' => '3.0.0', 'require' => ['illuminate/support' => '^12.0']],
                ];

            return [
                'status' => 200,
                'body' => json_encode(['packages' => [$package => $releases]], JSON_THROW_ON_ERROR),
                'headers' => [],
            ];
        });

        $candidate = (new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client))->build(new \DateTimeImmutable('2026-08-30'));
        self::assertIsArray($candidate['packages'] ?? null);
        $packages = $candidate['packages'];
        self::assertIsArray($packages['acme/widget'] ?? null);
        self::assertSame('1.0.0', $packages['acme/widget']['11'] ?? null);
        self::assertIsArray($packages['acme/or-widget'] ?? null);
        self::assertSame('2.0.0', $packages['acme/or-widget']['11'] ?? null);
        self::assertSame('3.0.0', $packages['acme/or-widget']['12'] ?? null);
    }

    public function test_composer_20_minified_p2_metadata_is_expanded_before_validation(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0', '12' => '12.0.0'],
                'php' => ['11' => '8.2.0', '12' => '8.2.0'],
                'acme/widget' => ['11' => '0.1.0', '12' => '0.1.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => [
            '11' => ['minimum' => '8.2.0'],
            '12' => ['minimum' => '8.2.0'],
        ]], JSON_THROW_ON_ERROR));

        $calls = 0;
        $client = new PackagistClient(static function (string $url) use (&$calls): array {
            $calls++;
            $releases = $calls === 1
                ? [
                    [
                        'name' => 'acme/widget',
                        'version' => '1.0.0',
                        'require' => ['illuminate/support' => '^11.0'],
                        'description' => 'retained until unset',
                    ],
                    ['version' => '2.0.0'],
                    [
                        'version' => '3.0.0',
                        'require' => ['illuminate/support' => '^12.0'],
                        'description' => '__unset',
                    ],
                    ['version' => '4.0.0', 'require' => '__unset'],
                ]
                : [
                    [
                        'name' => 'acme/widget',
                        'version' => '5.0.0',
                        'require' => ['illuminate/support' => '^12.0'],
                    ],
                    ['version' => '6.0.0', 'require' => '__unset'],
                ];

            return [
                'status' => 200,
                'body' => json_encode([
                    'minified' => 'composer/2.0',
                    'packages' => ['acme/widget' => $releases],
                    'next' => $calls === 1 ? '?page=2' : null,
                ], JSON_THROW_ON_ERROR),
                'headers' => [],
            ];
        });

        $candidate = (new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client))->build(new \DateTimeImmutable('2026-08-30'));
        self::assertIsArray($candidate['packages'] ?? null);
        $packages = $candidate['packages'];
        self::assertIsArray($packages['acme/widget'] ?? null);
        $widget = $packages['acme/widget'];
        self::assertSame('1.0.0', $widget['11'] ?? null);
        self::assertSame('3.0.0', $widget['12'] ?? null);
        self::assertSame(2, $calls);
    }

    public function test_unsupported_or_non_string_composer_minified_markers_fail(): void
    {
        foreach (['composer/1.0', true, null] as $marker) {
            $client = new PackagistClient(static function () use ($marker): array {
                return [
                    'status' => 200,
                    'body' => json_encode([
                        'minified' => $marker,
                        'packages' => ['acme/widget' => []],
                    ], JSON_THROW_ON_ERROR),
                    'headers' => [],
                ];
            });

            try {
                $client->releases('acme/widget');
                self::fail('Unsupported minified markers should fail.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('unsupported minified marker', $exception->getMessage());
            }
        }
    }

    public function test_no_compatible_release_fails_without_partial_write(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0'],
                'php' => ['11' => '8.2.0'],
                'acme/widget' => ['11' => '1.0.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $original = file_get_contents($matrixPath);
        $client = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => '^10.0']],
            ]]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);
        $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client);

        try {
            $builder->build();
            self::fail('A package with no compatible release should fail the refresh.');
        } catch (RuntimeException) {
            // A failed build must not have had a chance to write anything.
        }

        self::assertSame($original, file_get_contents($matrixPath));
    }

    public function test_unconstrained_development_tool_retains_reviewed_seed_and_special_entries_update(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            '$schema' => './packages.schema.json',
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '99.0.0', 'kind' => 'framework'],
                'php' => ['11' => '8.1.0', 'kind' => 'platform'],
                'phpunit/phpunit' => ['11' => '10.1.0', 'section' => 'require-dev'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => 'v8.2']]], JSON_THROW_ON_ERROR));
        $client = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['phpunit/phpunit' => [
                ['name' => 'phpunit/phpunit', 'version' => '10.1.0', 'replace' => ['illuminate/support' => '*'], 'provide' => ['illuminate/contracts' => '*']],
            ]]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);
        $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client);

        $candidate = $builder->build(new \DateTimeImmutable('2026-08-30'));
        self::assertIsArray($candidate['packages'] ?? null);
        self::assertSame(['$schema', 'generatedAt', 'packages'], array_keys($candidate));
        $packages = $candidate['packages'];
        self::assertIsArray($packages['laravel/framework'] ?? null);
        self::assertIsArray($packages['php'] ?? null);
        self::assertIsArray($packages['phpunit/phpunit'] ?? null);
        self::assertSame('11.0.0', $packages['laravel/framework']['11']);
        self::assertSame('8.2.0', $packages['php']['11']);
        self::assertSame('10.1.0', $packages['phpunit/phpunit']['11']);
        self::assertSame(['laravel/framework', 'php', 'phpunit/phpunit'], array_keys($packages));
    }

    public function test_malformed_p2_and_invalid_laravel_constraint_are_operational_failures(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0'],
                'php' => ['11' => '8.2.0'],
                'acme/widget' => ['11' => '1.0.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $cases = [
            'malformed JSON' => '{',
            'missing release list' => json_encode(['packages' => ['acme/widget' => ['unexpected' => true]]], JSON_THROW_ON_ERROR),
            'invalid constraint' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => 'not-a-constraint'],
            ]]]], JSON_THROW_ON_ERROR),
            'null constraint' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => null],
            ]]]], JSON_THROW_ON_ERROR),
            'non-string constraint' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => ['^11.0']],
            ]]]], JSON_THROW_ON_ERROR),
            'empty version' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '', 'require' => ['illuminate/support' => '^11.0'],
            ]]]], JSON_THROW_ON_ERROR),
            'non-string normalized version' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '1.0.0', 'version_normalized' => true, 'require' => ['illuminate/support' => '^11.0'],
            ]]]], JSON_THROW_ON_ERROR),
            'conflicting versions' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '1.0.0', 'version_normalized' => '2.0.0.0', 'require' => ['illuminate/support' => '^11.0'],
            ]]]], JSON_THROW_ON_ERROR),
            'self.version requirement' => json_encode(['packages' => ['acme/widget' => [[
                'name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => 'self.version'],
            ]]]], JSON_THROW_ON_ERROR),
        ];

        foreach ($cases as $body) {
            $client = new PackagistClient(static function () use ($body): array {
                return ['status' => 200, 'body' => $body, 'headers' => []];
            });
            $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client);

            try {
                $builder->build();
                self::fail('Malformed Packagist data should fail the refresh.');
            } catch (RuntimeException) {
                self::assertFileExists($matrixPath);
            }
        }
    }

    public function test_http_transport_exposes_rate_limit_and_size_failures(): void
    {
        $rateLimited = new PackagistClient(static fn (): array => [
            'status' => 429,
            'body' => '{}',
            'headers' => ['x-ratelimit-remaining: 0', 'retry-after: 60'],
        ], static function (): void {});

        try {
            $rateLimited->releases('acme/widget');
            self::fail('Rate-limited Packagist responses should fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('rate limit exhausted', strtolower($exception->getMessage()));
            self::assertStringContainsString('Retry after 60', $exception->getMessage());
        }

        $oversized = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => str_repeat('x', 8_388_609),
            'headers' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $oversized->releases('acme/widget');
    }

    public function test_transient_failures_retry_with_bounded_backoff_and_malformed_data_does_not_retry(): void
    {
        $calls = 0;
        $delays = [];
        $client = new PackagistClient(
            static function () use (&$calls): array {
                $calls++;

                if ($calls === 1) {
                    return ['status' => 500, 'body' => '{}', 'headers' => []];
                }

                if ($calls === 2) {
                    return ['status' => 429, 'body' => '{}', 'headers' => ['x-ratelimit-remaining: 0', 'retry-after: 60']];
                }

                return ['status' => 200, 'body' => json_encode(['packages' => ['acme/widget' => [
                    ['name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => '^11.0']],
                ]]], JSON_THROW_ON_ERROR), 'headers' => []];
            },
            static function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            },
        );

        self::assertCount(1, $client->releases('acme/widget'));
        self::assertSame(3, $calls);
        self::assertSame([100, 5_000], $delays);

        $malformedCalls = 0;
        $malformed = new PackagistClient(static function () use (&$malformedCalls): array {
            $malformedCalls++;

            return ['status' => 200, 'body' => '{', 'headers' => []];
        }, static function (): void {});

        try {
            $malformed->releases('acme/widget');
            self::fail('Malformed JSON should fail without retries.');
        } catch (RuntimeException) {
            self::assertSame(1, $malformedCalls);
        }

        $clientErrorCalls = 0;
        $clientError = new PackagistClient(static function () use (&$clientErrorCalls): array {
            $clientErrorCalls++;

            return ['status' => 400, 'body' => '{}', 'headers' => []];
        }, static function (): void {});

        try {
            $clientError->releases('acme/widget');
            self::fail('A non-rate-limited 4xx response should fail without retries.');
        } catch (RuntimeException) {
            self::assertSame(1, $clientErrorCalls);
        }

        $timeoutCalls = 0;
        $timeout = new PackagistClient(static function () use (&$timeoutCalls): array {
            $timeoutCalls++;

            if ($timeoutCalls === 1) {
                throw new HttpSourceException('connection timed out', null, [], true, 'transport');
            }

            return ['status' => 200, 'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => '^11.0']],
            ]]], JSON_THROW_ON_ERROR), 'headers' => []];
        }, static function (): void {});

        self::assertCount(1, $timeout->releases('acme/widget'));
        self::assertSame(2, $timeoutCalls);
    }

    public function test_structured_http_statuses_control_retries_without_url_message_matching(): void
    {
        $statuses = [
            403 => ['headers' => ['x-ratelimit-remaining: 1'], 'attempts' => 1],
            408 => ['headers' => ['retry-after: 1'], 'attempts' => 3],
            425 => ['headers' => ['retry-after: 1'], 'attempts' => 3],
            429 => ['headers' => ['retry-after: 1'], 'attempts' => 3],
            503 => ['headers' => ['retry-after: 1'], 'attempts' => 3],
        ];

        foreach ($statuses as $status => $expected) {
            $calls = 0;
            $delays = [];
            $client = new PackagistClient(static function () use (&$calls, $status): array {
                $calls++;

                return ['status' => $status, 'body' => '{}', 'headers' => ['retry-after: 1', 'x-ratelimit-remaining: 1']];
            }, static function (int $milliseconds) use (&$delays): void {
                $delays[] = $milliseconds;
            });

            try {
                $client->releases('acme/widget');
                self::fail(sprintf('HTTP %d should fail after its retry policy.', $status));
            } catch (RuntimeException) {
                self::assertSame($expected['attempts'], $calls, sprintf('Unexpected attempts for HTTP %d.', $status));
            }

            self::assertCount(max(0, $expected['attempts'] - 1), $delays);
        }

        $rateLimitCalls = 0;
        $rateLimitDelays = [];
        $rateLimited = new PackagistClient(static function () use (&$rateLimitCalls): array {
            $rateLimitCalls++;

            return ['status' => 403, 'body' => '{}', 'headers' => ['x-ratelimit-remaining: 0', 'retry-after: 1']];
        }, static function (int $milliseconds) use (&$rateLimitDelays): void {
            $rateLimitDelays[] = $milliseconds;
        });

        try {
            $rateLimited->releases('acme/widget');
            self::fail('An exhausted 403 rate limit should fail after retries.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('rate limit exhausted', strtolower($exception->getMessage()));
        }

        self::assertSame(3, $rateLimitCalls);
        self::assertSame([1_000, 1_000], $rateLimitDelays);

        $urlNamedTimeoutCalls = 0;
        $urlNamedTimeout = new PackagistClient(static function () use (&$urlNamedTimeoutCalls): array {
            $urlNamedTimeoutCalls++;

            return ['status' => 400, 'body' => '{}', 'headers' => []];
        }, static function (): void {});

        try {
            $urlNamedTimeout->releases('acme/timeout');
            self::fail('A 400 response should fail without URL-based retries.');
        } catch (RuntimeException) {
            self::assertSame(1, $urlNamedTimeoutCalls);
        }

        $fetcher = new HttpSourceFetcher(static fn (): array => [
            'status' => 503,
            'body' => 'unavailable',
            'headers' => ['Retry-After: 2', 'X-RateLimit-Remaining: 7'],
        ]);

        try {
            $fetcher->fetch('https://repo.packagist.org/p2/acme/widget.json', 1024);
            self::fail('A structured HTTP source failure should be thrown.');
        } catch (HttpSourceException $exception) {
            self::assertSame(503, $exception->status);
            self::assertTrue($exception->transient);
            self::assertSame('2', $exception->retryAfter);
            self::assertSame('7', $exception->headers['x-ratelimit-remaining']);
        }

        try {
            new PackagistClient(null, null, 4);
            self::fail('Retry attempts above the safety cap should be rejected.');
        } catch (RuntimeException) {
            // Expected.
        }
    }

    public function test_every_non_special_package_is_queried_and_generated_at_is_a_real_date(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0'],
                'php' => ['11' => '8.2.0'],
                'acme/one' => ['11' => '0.1.0'],
                'acme/two' => ['11' => '0.2.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $queried = [];
        $client = new PackagistClient(static function (string $url) use (&$queried): array {
            $name = preg_replace('~^/p2/(.+)\.json$~', '$1', (string) parse_url($url, PHP_URL_PATH));
            $queried[] = $name;

            return ['status' => 200, 'body' => json_encode(['packages' => [
                $name => [['name' => $name, 'version' => '1.0.0', 'require' => ['illuminate/support' => '^11.0']]],
            ]], JSON_THROW_ON_ERROR), 'headers' => []];
        });
        $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath, $client);

        $candidate = $builder->build(new \DateTimeImmutable('2026-08-30'));
        self::assertSame(['acme/one', 'acme/two'], $queried);
        self::assertSame('2026-08-30', $candidate['generatedAt']);

        file_put_contents($matrixPath, str_replace('2026-01-01', '2026-02-31', (string) file_get_contents($matrixPath)));
        $this->expectException(RuntimeException::class);
        $builder->build();
    }

    public function test_artifact_validator_rejects_structural_and_metadata_tampering_without_writing(): void
    {
        $baseline = [
            '$schema' => './packages.schema.json',
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0', 'kind' => 'framework'],
                'php' => ['11' => '8.2.0', 'kind' => 'platform'],
                'acme/widget' => ['11' => '1.0.0', 'kind' => 'library'],
            ],
        ];
        [$matrixPath] = $this->files($baseline);
        $candidatePath = $matrixPath.'.candidate';
        $original = file_get_contents($matrixPath);
        $validator = new CompatibilityMatrixArtifactValidator;
        $candidate = $baseline;

        $cases = [];
        $cases['empty packages'] = array_replace($candidate, ['packages' => []]);
        $cases['unexpected package'] = array_replace($candidate, ['packages' => array_merge($candidate['packages'], ['acme/other' => ['11' => '1.0.0']])]);
        $missing = $candidate;
        unset($missing['packages']['acme/widget']);
        $cases['missing package'] = $missing;
        $missingDate = $candidate;
        unset($missingDate['generatedAt']);
        $cases['missing generatedAt'] = $missingDate;
        $malformedEntry = $candidate;
        $malformedEntry['packages']['acme/widget'] = 'not-an-object';
        $cases['malformed package entry'] = $malformedEntry;
        $invalidVersion = $candidate;
        $invalidVersion['packages']['acme/widget']['11'] = 'dev-main';
        $cases['invalid stable version'] = $invalidVersion;
        $changedMetadata = $candidate;
        $changedMetadata['packages']['acme/widget']['kind'] = 'application';
        $cases['changed metadata'] = $changedMetadata;

        foreach ($cases as $label => $invalid) {
            file_put_contents($candidatePath, json_encode($invalid, JSON_THROW_ON_ERROR).PHP_EOL);

            try {
                $validator->validate($matrixPath, $candidatePath);
                self::fail(sprintf('The validator should reject %s.', $label));
            } catch (RuntimeException) {
                self::assertSame($original, file_get_contents($matrixPath));
            }
        }

        file_put_contents($candidatePath, (string) $original);
        $validator->validate($matrixPath, $candidatePath);
        self::assertSame($original, file_get_contents($matrixPath));

        $refreshed = $baseline;
        $refreshed['generatedAt'] = '2026-08-30';
        $refreshed['packages']['laravel/framework']['11'] = '11.1.0';
        $refreshed['packages']['acme/widget']['11'] = '2.0.0';
        file_put_contents($candidatePath, json_encode($refreshed, JSON_THROW_ON_ERROR).PHP_EOL);
        $validator->validate($matrixPath, $candidatePath);

        $binary = dirname(__DIR__, 3).'/bin/validate-compat-matrix-artifact';
        $command = sprintf(
            '%s %s --baseline %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($binary),
            escapeshellarg($matrixPath),
            escapeshellarg($candidatePath),
        );
        exec($command.' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
    }

    public function test_writer_preserves_existing_file_mode(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => ['laravel/framework' => ['11' => '11.0.0'], 'php' => ['11' => '8.2.0']],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        chmod($matrixPath, 0640);
        $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath);

        $builder->write($builder->build(new \DateTimeImmutable('2026-08-30')));
        self::assertSame(0640, fileperms($matrixPath) & 0777);
    }

    public function test_fixture_transport_rejects_symlinked_source(): void
    {
        $directory = sys_get_temp_dir().'/compat-fixture-'.bin2hex(random_bytes(6));
        mkdir($directory.'/acme', 0777, true);
        $this->temporaryDirectories[] = $directory;
        file_put_contents($directory.'/real.json', '{}');

        $transport = new FixturePackagistTransport($directory);

        try {
            $transport('https://repo.packagist.org/p2/../secret.json', 1000);
            self::fail('Fixture traversal should be rejected.');
        } catch (RuntimeException) {
            // Expected: fixture paths are rooted and cannot traverse upward.
        }

        try {
            $transport('https://repo.packagist.org/p2/%2e%2e/secret.json', 1000);
            self::fail('Encoded fixture traversal should be rejected.');
        } catch (RuntimeException) {
            // Expected: encoded path separators and traversal are rejected.
        }

        if (! symlink($directory.'/real.json', $directory.'/acme/widget.json')) {
            self::markTestSkipped('The test environment does not allow symlinks.');
        }

        try {
            $transport('https://repo.packagist.org/p2/acme/widget.json', 1000);
            self::fail('A symlinked fixture should be rejected.');
        } catch (RuntimeException) {
            // Expected: fixture paths are rooted and do not follow links.
        }

        file_put_contents($directory.'/acme/target.json', '{}');
        if (! symlink($directory.'/acme', $directory.'/link')) {
            self::markTestSkipped('The test environment does not allow symlinks.');
        }

        $this->expectException(RuntimeException::class);
        $transport('https://repo.packagist.org/p2/link/target.json', 1000);
    }

    public function test_atomic_writer_rejects_symlink_destination(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => ['laravel/framework' => ['11' => '11.0.0'], 'php' => ['11' => '8.2.0']],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $target = $matrixPath.'.target';
        copy($matrixPath, $target);
        unlink($matrixPath);

        if (! symlink($target, $matrixPath)) {
            self::markTestSkipped('The test environment does not allow symlinks.');
        }

        $builder = new CompatibilityMatrixBuilder($matrixPath, $phpPath);
        $this->expectException(RuntimeException::class);
        $builder->write(['generatedAt' => '2026-08-30', 'packages' => []]);
    }

    public function test_pagination_and_version_keyed_p2_payloads_are_supported(): void
    {
        $calls = [];
        $client = new PackagistClient(static function (string $url) use (&$calls): array {
            $calls[] = $url;
            $body = count($calls) === 1
                ? ['packages' => ['acme/widget' => ['1.0.0' => ['name' => 'acme/widget', 'version' => '1.0.0', 'meta' => 'z', 'require' => ['illuminate/support' => '^11.0']]]], 'next' => '?page=2']
                : ['packages' => ['acme/widget' => [
                    ['name' => 'acme/widget', 'version' => '1.0', 'version_normalized' => '1.0.0.0', 'meta' => 'a', 'require' => ['illuminate/support' => '^11.0']],
                    ['name' => 'acme/widget', 'version' => '2.0.0', 'require' => ['illuminate/support' => '^11.0']],
                ]]];

            return ['status' => 200, 'body' => json_encode($body, JSON_THROW_ON_ERROR), 'headers' => []];
        });

        $releases = $client->releases('acme/widget');
        self::assertCount(2, $releases);
        self::assertCount(2, $calls);
        self::assertSame('a', $releases[0]['meta']);
        self::assertSame('https://repo.packagist.org/p2/acme/widget.json?page=2', $calls[1]);
    }

    public function test_conflicting_duplicate_normalized_versions_fail_but_identical_duplicates_are_deterministic(): void
    {
        $client = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => 'v1.0.0', 'require' => ['illuminate/support' => '^11.0']],
                ['name' => 'acme/widget', 'version' => '1.0', 'version_normalized' => '1.0.0.0', 'require' => ['illuminate/support' => '^12.0']],
            ]]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);

        try {
            $client->releases('acme/widget');
            self::fail('Conflicting normalized duplicate requirements should fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('conflicting duplicate metadata', $exception->getMessage());
        }

        $identical = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => 'v1.0.0', 'meta' => 'z', 'require' => ['illuminate/support' => '^11.0']],
                ['name' => 'acme/widget', 'version' => '1.0', 'version_normalized' => '1.0.0.0', 'meta' => 'a', 'require' => ['illuminate/support' => '^11.0']],
            ]]], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);

        $releases = $identical->releases('acme/widget');
        self::assertCount(1, $releases);
        self::assertSame('a', $releases[0]['meta']);
    }

    public function test_relative_pagination_stays_on_the_packagist_p2_path(): void
    {
        $calls = [];
        $client = new PackagistClient(static function (string $url) use (&$calls): array {
            $calls[] = $url;

            if (count($calls) === 1) {
                return ['status' => 200, 'body' => json_encode([
                    'packages' => ['acme/widget' => [['name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => '^11.0']]]],
                    'next' => 'page-2.json',
                ], JSON_THROW_ON_ERROR), 'headers' => []];
            }

            return ['status' => 200, 'body' => json_encode(['packages' => ['acme/widget' => [
                ['name' => 'acme/widget', 'version' => '2.0.0', 'require' => ['illuminate/support' => '^11.0']],
            ]]], JSON_THROW_ON_ERROR), 'headers' => []];
        });

        self::assertCount(2, $client->releases('acme/widget'));
        self::assertSame('https://repo.packagist.org/p2/acme/page-2.json', $calls[1]);

        $unsafe = new PackagistClient(static fn (): array => [
            'status' => 200,
            'body' => json_encode([
                'packages' => ['acme/widget' => [['name' => 'acme/widget', 'version' => '1.0.0', 'require' => ['illuminate/support' => '^11.0']]]],
                'next' => 'https://repo.packagist.org/packages/acme/widget.json',
            ], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);

        $this->expectException(RuntimeException::class);
        $unsafe->releases('acme/widget');
    }

    public function test_cli_fixture_write_and_check_exit_codes(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => [
                'laravel/framework' => ['11' => '11.0.0'],
                'php' => ['11' => '8.2.0'],
                'acme/widget' => ['11' => '0.1.0'],
            ],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $fixture = dirname($matrixPath).'/p2';
        mkdir($fixture.'/acme', 0777, true);
        file_put_contents($fixture.'/acme/widget.json', json_encode(['packages' => ['acme/widget' => [
            ['name' => 'acme/widget', 'version' => '1.1.0', 'require' => ['illuminate/support' => '^11.0']],
        ]]], JSON_THROW_ON_ERROR));
        $root = dirname(dirname(dirname($matrixPath)));
        $binary = dirname(__DIR__, 3).'/bin/build-compat-matrix';
        $command = sprintf(
            '%s %s --root %s --output %s --fixture-dir %s --now=2026-08-30',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($binary),
            escapeshellarg($root),
            escapeshellarg($matrixPath),
            escapeshellarg(dirname($fixture)),
        );
        exec($command.' --check 2>&1', $output, $status);
        self::assertSame(1, $status, implode("\n", $output));
        exec($command.' --write 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertStringContainsString('"11": "1.1.0"', (string) file_get_contents($matrixPath));

        exec($command.' --check 2>&1', $checkOutput, $checkStatus);
        self::assertSame(0, $checkStatus, implode("\n", $checkOutput));

        foreach ([$command.' --check --dry-run', $command.' --check --check', $command.' --write --check', $command.' --dry-run --dry-run', $command.' --write --write'] as $conflicting) {
            exec($conflicting.' 2>&1', $conflictOutput, $conflictStatus);
            self::assertSame(2, $conflictStatus, implode("\n", $conflictOutput));
        }

        exec($command.' 2>&1', $missingModeOutput, $missingModeStatus);
        self::assertSame(2, $missingModeStatus, implode("\n", $missingModeOutput));

        $original = file_get_contents($matrixPath);
        file_put_contents($fixture.'/acme/widget.json', '{');
        exec($command.' --write 2>&1', $operationalOutput, $operationalStatus);
        self::assertSame(2, $operationalStatus, implode("\n", $operationalOutput));
        self::assertSame($original, file_get_contents($matrixPath));
    }

    public function test_cli_rejects_root_symlink_components_including_trailing_and_intermediate_links(): void
    {
        [$matrixPath, $phpPath] = $this->files([
            'generatedAt' => '2026-01-01',
            'packages' => ['laravel/framework' => ['11' => '11.0.0'], 'php' => ['11' => '8.2.0']],
        ]);
        file_put_contents($phpPath, json_encode(['php' => ['11' => ['minimum' => '8.2.0']]], JSON_THROW_ON_ERROR));
        $root = dirname(dirname(dirname($matrixPath)));
        $holder = sys_get_temp_dir().'/compat-root-links-'.bin2hex(random_bytes(6));
        mkdir($holder, 0777, true);
        $this->temporaryDirectories[] = $holder;
        $leafLink = $holder.'/root-link';

        if (! symlink($root, $leafLink) || ! symlink(sys_get_temp_dir(), $holder.'/parent-link')) {
            self::markTestSkipped('The test environment does not allow symlinks.');
        }

        $binary = dirname(__DIR__, 3).'/bin/build-compat-matrix';

        foreach ([$leafLink, $leafLink.'/', $holder.'/parent-link/'.basename($root)] as $unsafeRoot) {
            $command = sprintf(
                '%s %s --root %s --output %s --check',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($binary),
                escapeshellarg($unsafeRoot),
                escapeshellarg($matrixPath),
            );
            exec($command.' 2>&1', $output, $status);
            self::assertSame(2, $status, implode("\n", $output));
        }
    }

    public function test_refresh_workflow_is_sha_pinned_and_keeps_write_permissions_isolated(): void
    {
        $workflowPath = dirname(__DIR__, 3).'/.github/workflows/compatibility-matrix.yml';
        $workflow = file_get_contents($workflowPath);
        self::assertIsString($workflow);
        self::assertStringContainsString('permissions: {}', $workflow);
        self::assertStringContainsString('needs: refresh', $workflow);
        self::assertStringContainsString('composer install --prefer-dist --no-interaction --no-progress --no-scripts', $workflow);
        self::assertStringContainsString('persist-credentials: false', $workflow);
        self::assertStringContainsString('add-paths: resources/compat/packages.json', $workflow);
        self::assertStringNotContainsString('labels:', $workflow);
        self::assertStringNotContainsString('uses: actions/checkout@v', $workflow);
        self::assertStringNotContainsString('uses: actions/upload-artifact@v', $workflow);
        self::assertStringNotContainsString('uses: actions/download-artifact@v', $workflow);

        preg_match_all('/^\s*uses:\s+[^@\s]+@([0-9a-f]{40})\s*(?:#.*)?$/m', $workflow, $matches);
        self::assertNotEmpty($matches[1]);
        self::assertCount(substr_count($workflow, 'uses:'), $matches[1]);
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return array{0: string, 1: string}
     */
    private function files(array $matrix): array
    {
        $directory = sys_get_temp_dir().'/compat-matrix-'.bin2hex(random_bytes(6));
        mkdir($directory.'/resources/compat', 0777, true);
        $this->temporaryDirectories[] = $directory;
        $matrixPath = $directory.'/resources/compat/packages.json';
        $phpPath = $directory.'/resources/compat/php.json';
        file_put_contents($matrixPath, json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return [$matrixPath, $phpPath];
    }

    private function removeDirectory(string $directory): void
    {
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $name) {
            $path = $directory.'/'.$name;

            if (is_dir($path) && ! is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
