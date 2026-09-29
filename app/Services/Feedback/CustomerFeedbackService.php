<?php

namespace App\Services\Feedback;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerFeedbackService
{
    /**
     * Submit feedback for one completed appointment owned
     * by the authenticated customer.
     *
     * @param array<string, mixed> $data
     */
    public function submit(
        User $customer,
        int $appointmentId,
        array $data
    ): Feedback {
        return DB::transaction(
            function () use (
                $customer,
                $appointmentId,
                $data
            ): Feedback {
                /*
                |--------------------------------------------------------------------------
                | Lock Owned Appointment
                |--------------------------------------------------------------------------
                |
                | Scoping by customer_id provides tenant isolation.
                |
                | Another customer's appointment therefore returns 404 rather
                | than revealing whether that appointment exists.
                |
                */

                $appointment =
                    Appointment::query()
                        ->where(
                            'customer_id',
                            $customer->id
                        )
                        ->whereKey(
                            $appointmentId
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Completed Appointments Only
                |--------------------------------------------------------------------------
                */

                if (
                    $appointment->status
                    !== AppointmentStatus::Completed
                ) {
                    $this->conflict(
                        'Feedback can only be submitted for a completed appointment.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | One Review Per Appointment
                |--------------------------------------------------------------------------
                |
                | The appointment row lock serializes concurrent attempts for
                | this appointment. The database UNIQUE constraint remains the
                | final line of defense.
                |
                */

                $alreadyReviewed =
                    Feedback::query()
                        ->where(
                            'appointment_id',
                            $appointment->id
                        )
                        ->exists();

                if ($alreadyReviewed) {
                    $this->conflict(
                        'Feedback has already been submitted for this appointment.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Create Feedback
                |--------------------------------------------------------------------------
                |
                | Only review content comes from validated client input.
                |
                | Ownership is derived entirely from the locked appointment
                | and authenticated user.
                |
                */

                $feedback =
                    new Feedback([
                        'rating' =>
                            $data['rating'],

                        'comment' =>
                            $data['comment']
                            ?? null,
                    ]);

                $feedback->appointment_id =
                    $appointment->id;

                $feedback->customer_id =
                    $customer->id;

                $feedback->shop_id =
                    $appointment->shop_id;

                $feedback->save();

                return $feedback->load([
                    'appointment',
                    'shop',
                ]);
            },
            3
        );
    }

    /**
     * Invalid appointment state produces HTTP 409 Conflict.
     */
    private function conflict(
        string $message
    ): never {
        throw new HttpException(
            Response::HTTP_CONFLICT,
            $message
        );
    }
}