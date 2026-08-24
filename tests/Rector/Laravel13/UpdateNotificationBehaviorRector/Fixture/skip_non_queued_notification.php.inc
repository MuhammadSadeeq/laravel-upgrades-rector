<?php

namespace App\Fixtures\Laravel13\UpdateNotificationBehaviorRector;

use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class InvoiceNotification extends Notification
{
}

?>
