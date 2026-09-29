<?php

namespace App\Observers\Audit;

use App\Enums\AuditEvent;
use App\Models\Feedback;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class FeedbackAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function created(
        Feedback $feedback
    ): void {
        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::FeedbackSubmitted,

                auditable:
                    $feedback,

                metadata: [
                    'appointment_id' =>
                        $feedback
                            ->appointment_id,

                    'shop_id' =>
                        $feedback
                            ->shop_id,

                    'rating' =>
                        $feedback
                            ->rating,
                ],
            );
    }
}