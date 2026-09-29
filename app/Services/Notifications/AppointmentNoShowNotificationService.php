<?php

namespace App\Services\Notifications;

use App\Enums\InAppNotificationType;
use App\Models\Appointment;
use App\Models\InAppNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Throwable;

class AppointmentNoShowNotificationService
{
    public function notifyCustomer(
        Appointment $appointment
    ): ?InAppNotification {
        try {
            $appointment->loadMissing([
                'customer',
                'shop',
            ]);

            $recipient =
                $appointment->customer;

            if (! $recipient instanceof User) {
                return null;
            }

            $shopName =
                $appointment->shop?->name
                ?? 'the shop';

            $type =
                InAppNotificationType::AppointmentNoShow;

            $dedupeKey =
                sprintf(
                    'inapp:appointment:%d:%s:user:%d',
                    $appointment->id,
                    $type->value,
                    $recipient->id
                );

            $existing =
                InAppNotification::query()
                    ->where(
                        'dedupe_key',
                        $dedupeKey
                    )
                    ->first();

            if ($existing !== null) {
                return $existing;
            }

            $notification =
                new InAppNotification([
                    'type' =>
                        $type,

                    'title' =>
                        $type->label(),

                    'message' =>
                        sprintf(
                            'The shop marked your appointment at %s on %s at %s as a no-show.',
                            $shopName,
                            $this->formatAppointmentDate(
                                $appointment
                            ),
                            $this->formatAppointmentTime(
                                $appointment
                            )
                        ),
                ]);

            $notification->user_id =
                $recipient->id;

            $notification->appointment_id =
                $appointment->id;

            $notification->reschedule_request_id =
                null;

            $notification->feedback_id =
                null;

            $notification->promo_id =
                null;

            $notification->dedupe_key =
                $dedupeKey;

            $notification->read_at =
                null;

            try {
                $notification->save();
            } catch (QueryException $exception) {
                $existing =
                    InAppNotification::query()
                        ->where(
                            'dedupe_key',
                            $dedupeKey
                        )
                        ->first();

                if ($existing !== null) {
                    return $existing;
                }

                throw $exception;
            }

            return $notification;
        } catch (Throwable $exception) {
            /*
             * Notification infrastructure must never undo
             * the successful No-show state transition.
             */
            report(
                $exception
            );

            return null;
        }
    }

    private function formatAppointmentDate(
        Appointment $appointment
    ): string {
        return CarbonImmutable::parse(
            $appointment
                ->appointment_date
                ->toDateString(),
            config(
                'app.timezone',
                'UTC'
            )
        )->format(
            'M j, Y'
        );
    }

    private function formatAppointmentTime(
        Appointment $appointment
    ): string {
        return CarbonImmutable::parse(
            $appointment
                ->appointment_date
                ->toDateString()
            .' '
            .$appointment->starts_at,
            config(
                'app.timezone',
                'UTC'
            )
        )->format(
            'g:i A'
        );
    }
}