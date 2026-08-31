<?php

namespace App\Laravel13RuleFixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class SynchronousNotification extends Notification
{
    use Queueable;
}

final class QueueableUnrelatedClass
{
    use Queueable;
}

final class ExplicitlyKeepMissingModelsNotification extends Notification
{
    public bool $deleteWhenMissingModels = false;
}
