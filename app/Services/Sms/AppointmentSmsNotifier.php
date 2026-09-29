<?php

namespace App\Services\Sms;

use App\Enums\SmsNotificationType;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Models\SmsNotification;
use App\Models\User;
use App\Support\AppointmentReminderKey;
use Carbon\CarbonImmutable;
use Throwable;

class AppointmentSmsNotifier
{
    private readonly SmsTemplateRenderer $templates;

    public function __construct(
        private readonly SmsNotificationService $notifications,
        ?SmsTemplateRenderer $templates = null
    ) {
        /*
         * Keep the renderer optional so existing focused tests that manually
         * construct this notifier with a mocked SmsNotificationService remain
         * compatible.
         */
        $this->templates =
            $templates
            ?? app(
                SmsTemplateRenderer::class
            );
    }

    public function bookingReceived(
        Appointment $appointment
    ): void {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $shopName =
            $appointment->shop?->name
            ?? 'the selected shop';

        $message =
            $this->templates->render(
                SmsNotificationType::BookingReceived,
                [
                    'shop_name' =>
                        $shopName,

                    'service_name' =>
                        $appointment->service_name,

                    'appointment_date' =>
                        $this->formatAppointmentDate(
                            $appointment
                        ),

                    'appointment_time' =>
                        $this->formatAppointmentTime(
                            $appointment
                        ),
                ]
            );

        $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::BookingReceived,

            message:
                $message,
        );
    }

    public function appointmentConfirmed(
        Appointment $appointment
    ): void {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        $message =
            $this->templates->render(
                SmsNotificationType::AppointmentConfirmed,
                [
                    'shop_name' =>
                        $shopName,

                    'appointment_date' =>
                        $this->formatAppointmentDate(
                            $appointment
                        ),

                    'appointment_time' =>
                        $this->formatAppointmentTime(
                            $appointment
                        ),
                ]
            );

        $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::AppointmentConfirmed,

            message:
                $message,
        );
    }

    public function appointmentDeclined(
        Appointment $appointment
    ): void {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        $message =
            $this->templates->render(
                SmsNotificationType::AppointmentDeclined,
                [
                    'shop_name' =>
                        $shopName,

                    'appointment_date' =>
                        $this->formatAppointmentDate(
                            $appointment
                        ),

                    'appointment_time' =>
                        $this->formatAppointmentTime(
                            $appointment
                        ),
                ]
            );

        $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::AppointmentDeclined,

            message:
                $message,
        );
    }

    public function appointmentCancelled(
        Appointment $appointment
    ): void {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        $message =
            $this->templates->render(
                SmsNotificationType::AppointmentCancelled,
                [
                    'shop_name' =>
                        $shopName,

                    'appointment_date' =>
                        $this->formatAppointmentDate(
                            $appointment
                        ),

                    'appointment_time' =>
                        $this->formatAppointmentTime(
                            $appointment
                        ),
                ]
            );

        $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::AppointmentCancelled,

            message:
                $message,
        );
    }

    /**
     * Queue one reminder for this exact appointment schedule.
     */
    public function appointmentReminder(
        Appointment $appointment
    ): ?SmsNotification {
        $appointment->loadMissing([
            'customer',
            'shop',
        ]);

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        $message =
            $this->templates->render(
                SmsNotificationType::AppointmentReminder,
                [
                    'shop_name' =>
                        $shopName,

                    'appointment_date' =>
                        $this->formatAppointmentDate(
                            $appointment
                        ),

                    'appointment_time' =>
                        $this->formatAppointmentTime(
                            $appointment
                        ),
                ]
            );

        return $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::AppointmentReminder,

            message:
                $message,

            dedupeKey:
                AppointmentReminderKey::for(
                    $appointment
                ),
        );
    }

    public function rescheduleApproved(
        RescheduleRequest $rescheduleRequest
    ): void {
        $rescheduleRequest->loadMissing([
            'appointment.customer',
            'appointment.shop',
        ]);

        $appointment =
            $rescheduleRequest->appointment;

        if ($appointment === null) {
            return;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        $message =
            $this->templates->render(
                SmsNotificationType::RescheduleApproved,
                [
                    'appointment_date' =>
                        $this->formatAppointmentDate(
                            $appointment
                        ),

                    'appointment_time' =>
                        $this->formatAppointmentTime(
                            $appointment
                        ),

                    'shop_name' =>
                        $shopName,
                ]
            );

        $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::RescheduleApproved,

            message:
                $message,
        );
    }

    public function rescheduleRejected(
        RescheduleRequest $rescheduleRequest
    ): void {
        $rescheduleRequest->loadMissing([
            'appointment.customer',
            'appointment.shop',
        ]);

        $appointment =
            $rescheduleRequest->appointment;

        if ($appointment === null) {
            return;
        }

        $shopName =
            $appointment->shop?->name
            ?? 'the shop';

        $requestedDate =
            $rescheduleRequest
                ->requested_date
                ?->format(
                    'M j, Y'
                )
            ?? 'the requested date';

        $requestedTime =
            $this->formatTime(
                $rescheduleRequest
                    ->requested_starts_at
            );

        $message =
            $this->templates->render(
                SmsNotificationType::RescheduleRejected,
                [
                    'requested_date' =>
                        $requestedDate,

                    'requested_time' =>
                        $requestedTime,

                    'shop_name' =>
                        $shopName,
                ]
            );

        $this->queueSafely(
            appointment:
                $appointment,

            type:
                SmsNotificationType::RescheduleRejected,

            message:
                $message,
        );
    }

    /**
     * Notification infrastructure must never invalidate
     * an already committed appointment workflow.
     */
    private function queueSafely(
        Appointment $appointment,
        SmsNotificationType $type,
        string $message,
        ?string $dedupeKey = null
    ): ?SmsNotification {
        try {
            $appointment->loadMissing([
                'customer',
            ]);

            $customer =
                $appointment->customer;

            if (! $customer instanceof User) {
                return null;
            }

            return $this->notifications->queue(
                recipient:
                    $customer,

                type:
                    $type,

                message:
                    $message,

                appointment:
                    $appointment,

                dedupeKey:
                    $dedupeKey,
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            return null;
        }
    }

    private function formatAppointmentDate(
        Appointment $appointment
    ): string {
        if (
            $appointment->appointment_date
            === null
        ) {
            return 'the scheduled date';
        }

        return $appointment
            ->appointment_date
            ->format(
                'M j, Y'
            );
    }

    private function formatAppointmentTime(
        Appointment $appointment
    ): string {
        return $this->formatTime(
            $appointment->starts_at
        );
    }

    private function formatTime(
        mixed $time
    ): string {
        if (
            ! is_string($time)
            || trim($time) === ''
        ) {
            return 'the scheduled time';
        }

        try {
            return CarbonImmutable::parse(
                '2000-01-01 '
                .trim($time)
            )->format(
                'g:i A'
            );
        } catch (Throwable) {
            return trim(
                $time
            );
        }
    }
}