<?php

namespace App\Observers;

use App\Models\Feedback;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class FeedbackObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly InAppNotificationService $inAppNotifications
    ) {}

    /**
     * A newly submitted review should alert the reviewed shop owner.
     */
    public function created(
        Feedback $feedback
    ): void {
        $this->inAppNotifications
            ->reviewReceived(
                $feedback
            );
    }
}