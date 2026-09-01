<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Laravel 13 can drop queued notifications when their subject model is
 * missing. This legacy notification deliberately has no deletion policy.
 */
final class OrderUpdated extends Notification implements ShouldQueue
{
    use Queueable;
}
