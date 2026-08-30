<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\LocalDiskDefaultRootRule;

/** @extends Laravel12RuleTestCase<LocalDiskDefaultRootRule> */
final class LocalDiskDefaultRootRuleTest extends Laravel12RuleTestCase
{
    private bool $localDiskRootConfigured = false;

    private ?bool $localDiskIsDefault = true;

    protected function getRule(): LocalDiskDefaultRootRule
    {
        return new LocalDiskDefaultRootRule($this->localDiskRootConfigured, $this->localDiskIsDefault);
    }

    public function test_local_disk_usage_is_reported_when_root_is_not_explicit(): void
    {
        $this->analyse([__DIR__.'/Fixture/local-disk-positive.php'], [[
            'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").',
            9,
            'Define disks.local.root explicitly to preserve storage/app behaviour.',
        ]]);
    }

    public function test_default_disk_usage_is_reported_but_named_non_local_disk_is_safe_when_local_is_default(): void
    {
        $this->analyse([__DIR__.'/Fixture/local-disk-default.php'], [[
            'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").',
            9,
            'Define disks.local.root explicitly to preserve storage/app behaviour.',
        ], [
            'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").',
            10,
            'Define disks.local.root explicitly to preserve storage/app behaviour.',
        ]]);
    }

    public function test_default_disk_usage_is_safe_when_s3_is_the_project_default(): void
    {
        $this->localDiskIsDefault = false;
        $this->analyse([__DIR__.'/Fixture/local-disk-default-s3.php'], []);
    }

    public function test_explicit_local_disk_usage_is_reported_even_when_s3_is_the_default(): void
    {
        $this->localDiskIsDefault = false;
        $this->analyse([__DIR__.'/Fixture/local-disk-positive.php'], [[
            'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").',
            9,
            'Define disks.local.root explicitly to preserve storage/app behaviour.',
        ]]);
    }

    public function test_typed_filesystem_manager_local_disk_usage_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/local-disk-manager.php'], [[
            'If no "local" disk is explicitly defined, Laravel now defaults it to storage_path("app/private").',
            9,
            'Define disks.local.root explicitly to preserve storage/app behaviour.',
        ]]);
    }

    public function test_explicit_root_context_suppresses_local_usage_finding(): void
    {
        $this->localDiskRootConfigured = true;
        $this->analyse([__DIR__.'/Fixture/local-disk-explicit-root.php'], []);
    }
}
