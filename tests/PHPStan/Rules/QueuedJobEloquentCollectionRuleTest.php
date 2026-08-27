<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\QueuedJobEloquentCollectionRule;

/** @extends Laravel13RuleTestCase<QueuedJobEloquentCollectionRule> */
final class QueuedJobEloquentCollectionRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): QueuedJobEloquentCollectionRule
    {
        return new QueuedJobEloquentCollectionRule;
    }

    public function test_properties_and_promoted_parameters_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/Jobs/CollectionPayloadJob.php'], [
            [
                'Queued jobs carrying an Eloquent Collection require review in Laravel 13.',
                10,
                'Prefer a model identifier or a plain collection payload when serializing queued jobs.',
            ],
            [
                'Queued jobs carrying an Eloquent Collection require review in Laravel 13.',
                12,
                'Prefer a model identifier or a plain collection payload when serializing queued jobs.',
            ],
            [
                'Queued jobs carrying an Eloquent Collection require review in Laravel 13.',
                12,
                'Prefer a model identifier or a plain collection payload when serializing queued jobs.',
            ],
        ]);
    }

    public function test_plain_collection_job_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/Jobs/CollectionSafeJob.php'], []);
    }

    public function test_non_queued_collection_holder_is_safe(): void
    {
        $this->analyse([__DIR__.'/Fixture/Jobs/PlainCollectionPayload.php'], []);
    }
}
