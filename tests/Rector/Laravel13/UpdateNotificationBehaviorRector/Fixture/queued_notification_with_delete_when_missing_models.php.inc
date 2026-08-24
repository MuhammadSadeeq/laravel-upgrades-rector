<?php

namespace App\Fixtures\Laravel13\UpdateNotificationBehaviorRector;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class InvoiceNotification extends Notification implements ShouldQueue
{
}

?>
-----
<?php

namespace App\Fixtures\Laravel13\UpdateNotificationBehaviorRector;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

// Laravel 13: queued notifications now respect DeleteWhenMissingModels. Verify this notification should be deleted instead of failing when related models are missing.
#[DeleteWhenMissingModels]
class InvoiceNotification extends Notification implements ShouldQueue
{
}

?>
