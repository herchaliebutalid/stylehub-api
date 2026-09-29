<?php

namespace App\Observers\Audit;

use App\Enums\AuditEvent;
use App\Enums\RescheduleRequestStatus;
use App\Models\RescheduleRequest;
use App\Services\Audit\AuditLogger;
use BackedEnum;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RescheduleRequestAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function created(
        RescheduleRequest $request
    ): void {
        if (
            $request->status
            !== RescheduleRequestStatus::Pending
        ) {
            return;
        }

        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::RescheduleRequested,

                auditable:
                    $request,

                metadata:
                    $this->metadata(
                        $request
                    ),
            );
    }

    public function updated(
        RescheduleRequest $request
    ): void {
        if (
            ! $request->wasChanged(
                'status'
            )
        ) {
            return;
        }

        $event =
            match (
                $request->status
            ) {
                RescheduleRequestStatus::Approved =>
                    AuditEvent::RescheduleApproved,

                RescheduleRequestStatus::Rejected =>
                    AuditEvent::RescheduleRejected,

                RescheduleRequestStatus::Withdrawn =>
                    AuditEvent::RescheduleWithdrawn,

                RescheduleRequestStatus::Pending =>
                    null,
            };

        if ($event === null) {
            return;
        }

        $metadata =
            $this->metadata(
                $request
            );

        $previous =
            $request->getOriginal(
                'status'
            );

        $metadata[
            'previous_status'
        ] = $previous instanceof BackedEnum
            ? $previous->value
            : $previous;

        $metadata[
            'new_status'
        ] = $request
            ->status
            ->value;

        $this->audit
            ->recordCurrentSafely(
                event:
                    $event,

                auditable:
                    $request,

                metadata:
                    $metadata,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(
        RescheduleRequest $request
    ): array {
        return [
            'appointment_id' =>
                $request->appointment_id,

            'requested_date' =>
                $request
                    ->requested_date
                    ?->toDateString(),

            'requested_starts_at' =>
                $request
                    ->requested_starts_at,
        ];
    }
}