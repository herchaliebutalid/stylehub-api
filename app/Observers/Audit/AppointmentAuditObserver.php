<?php

namespace App\Observers\Audit;

use App\Enums\AppointmentStatus;
use App\Enums\AuditEvent;
use App\Models\Appointment;
use App\Services\Audit\AuditLogger;
use BackedEnum;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class AppointmentAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function created(
        Appointment $appointment
    ): void {
        if (
            $appointment->status
            !== AppointmentStatus::Pending
        ) {
            return;
        }

        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::AppointmentBooked,

                auditable:
                    $appointment,

                metadata:
                    $this->metadata(
                        $appointment
                    ),
            );
    }

    public function updated(
        Appointment $appointment
    ): void {
        if (
            ! $appointment->wasChanged(
                'status'
            )
        ) {
            return;
        }

        $event =
            match (
                $appointment->status
            ) {
                AppointmentStatus::Confirmed =>
                    AuditEvent::AppointmentConfirmed,

                AppointmentStatus::Declined =>
                    AuditEvent::AppointmentDeclined,

                AppointmentStatus::Cancelled =>
                    AuditEvent::AppointmentCancelled,

                AppointmentStatus::Completed =>
                    AuditEvent::AppointmentCompleted,

                AppointmentStatus::NoShow =>
                    AuditEvent::AppointmentNoShow,

                AppointmentStatus::Pending =>
                    null,
            };

        if ($event === null) {
            return;
        }

        $metadata =
            $this->metadata(
                $appointment
            );

        /*
        |--------------------------------------------------------------------------
        | Previous Status
        |--------------------------------------------------------------------------
        |
        | Laravel synchronizes the model's "original" attributes after a save.
        | getPrevious() preserves the values that existed immediately before
        | the most recent successful save, which is what an audit transition
        | must record.
        |
        */

        $previousAttributes =
            $appointment->getPrevious();

        $previousStatus =
            $previousAttributes[
                'status'
            ]
            ?? null;

        $metadata[
            'previous_status'
        ] =
            $previousStatus
                instanceof BackedEnum
                ? $previousStatus->value
                : $previousStatus;

        $metadata[
            'new_status'
        ] =
            $appointment
                ->status
                ->value;

        $this->audit
            ->recordCurrentSafely(
                event:
                    $event,

                auditable:
                    $appointment,

                metadata:
                    $metadata,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(
        Appointment $appointment
    ): array {
        return [
            'shop_id' =>
                $appointment->shop_id,

            'service_id' =>
                $appointment->service_id,

            'appointment_date' =>
                $appointment
                    ->appointment_date
                    ?->toDateString(),

            'starts_at' =>
                $appointment->starts_at,

            'status' =>
                $appointment
                    ->status
                    ->value,
        ];
    }
}