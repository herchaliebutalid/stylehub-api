<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Sms\AppointmentSmsNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class QueueAppointmentReminders extends Command
{
    protected $signature =
        'stylehub:sms:queue-reminders';

    protected $description =
        'Queue one SMS reminder for confirmed appointments approaching their scheduled time.';

    public function handle(
        AppointmentSmsNotifier $notifier
    ): int {
        $timezone =
            (string) config(
                'app.timezone',
                'UTC'
            );

        $leadMinutes =
            max(
                1,
                (int) config(
                    'sms.reminders.lead_minutes',
                    1440
                )
            );

        $minimumLeadMinutes =
            max(
                0,
                (int) config(
                    'sms.reminders.minimum_lead_minutes',
                    60
                )
            );

        if (
            $minimumLeadMinutes
            >= $leadMinutes
        ) {
            $this->error(
                'SMS reminder minimum lead time must be smaller than reminder lead time.'
            );

            return self::FAILURE;
        }

        $now =
            CarbonImmutable::now(
                $timezone
            );

        /*
         * Eligible range:
         *
         * now + 60 minutes
         * through
         * now + 24 hours
         *
         * This is more resilient than requiring an exact 24-hour moment.
         * If the scheduler was briefly unavailable, it can still create
         * the reminder later inside the allowed window.
         */

        $earliest =
            $now->addMinutes(
                $minimumLeadMinutes
            );

        $latest =
            $now->addMinutes(
                $leadMinutes
            );

        $queued = 0;

        Appointment::query()
            ->where(
                'status',
                AppointmentStatus::Confirmed
                    ->value
            )
            ->whereBetween(
                'appointment_date',
                [
                    $earliest
                        ->toDateString(),

                    $latest
                        ->toDateString(),
                ]
            )
            ->with([
                'customer',
                'shop',
            ])
            ->orderBy(
                'id'
            )
            ->chunkById(
                100,
                function (
                    $appointments
                ) use (
                    $notifier,
                    $timezone,
                    $earliest,
                    $latest,
                    &$queued
                ): void {
                    foreach (
                        $appointments
                        as $appointment
                    ) {
                        try {
                            if (
                                $appointment
                                    ->appointment_date
                                    === null
                                || ! is_string(
                                    $appointment
                                        ->starts_at
                                )
                            ) {
                                continue;
                            }

                            $startsAt =
                                CarbonImmutable::parse(
                                    $appointment
                                        ->appointment_date
                                        ->toDateString()
                                    .' '
                                    .$appointment
                                        ->starts_at,
                                    $timezone
                                );

                            if (
                                $startsAt
                                    ->lessThan(
                                        $earliest
                                    )
                                || $startsAt
                                    ->greaterThan(
                                        $latest
                                    )
                            ) {
                                continue;
                            }

                            $notification =
                                $notifier
                                    ->appointmentReminder(
                                        $appointment
                                    );

                            if (
                                $notification !== null
                                && $notification
                                    ->wasRecentlyCreated
                            ) {
                                $queued++;
                            }
                        } catch (Throwable $exception) {
                            /*
                             * One malformed appointment must not prevent
                             * reminders for every other appointment.
                             */

                            report(
                                $exception
                            );
                        }
                    }
                }
            );

        $this->info(
            "Queued {$queued} appointment reminder(s)."
        );

        return self::SUCCESS;
    }
}