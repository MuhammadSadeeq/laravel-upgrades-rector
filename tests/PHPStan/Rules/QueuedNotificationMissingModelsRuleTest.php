<?php

declare(strict_types=1);

namespace MuhammadSadeeq\LaravelUpgradesRector\Tests\PHPStan\Rules;

use MuhammadSadeeq\LaravelUpgradesRector\PHPStan\Rules\QueuedNotificationMissingModelsRule;

/** @extends Laravel13RuleTestCase<QueuedNotificationMissingModelsRule> */
final class QueuedNotificationMissingModelsRuleTest extends Laravel13RuleTestCase
{
    protected function getRule(): QueuedNotificationMissingModelsRule
    {
        return new QueuedNotificationMissingModelsRule;
    }

    public function test_queued_notification_without_missing_model_policy_is_reported(): void
    {
        require_once __DIR__.'/Fixture/queued-notification-positive.php';

        $this->analyse([__DIR__.'/Fixture/queued-notification-positive.php'], [
            [
                'Queued notifications may fail when their subject model is missing in Laravel 13.',
                9,
                'Add #[DeleteWhenMissingModels] or set $deleteWhenMissingModels = true when missing models should delete the job.',
            ],
            [
                'Queued notifications may fail when their subject model is missing in Laravel 13.',
                19,
                'Add #[DeleteWhenMissingModels] or set $deleteWhenMissingModels = true when missing models should delete the job.',
            ],
        ]);
    }

    public function test_queued_notifications_with_attribute_or_property_are_safe(): void
    {
        require_once __DIR__.'/Fixture/queued-notification-safe.php';

        $this->analyse([__DIR__.'/Fixture/queued-notification-safe.php'], []);
    }

    public function test_non_queued_notification_and_unrelated_classes_are_safe(): void
    {
        require_once __DIR__.'/Fixture/queued-notification-edge.php';

        $this->analyse([__DIR__.'/Fixture/queued-notification-edge.php'], []);
    }
}
