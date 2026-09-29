<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_can_be_created_for_completed_appointment(): void
    {
        [$appointment] =
            $this->completedAppointment();

        $feedback =
            Feedback::factory()->create([
                'appointment_id' =>
                    $appointment->id,

                'customer_id' =>
                    $appointment->customer_id,

                'shop_id' =>
                    $appointment->shop_id,

                'rating' =>
                    5,

                'comment' =>
                    'Excellent service.',
            ]);

        $this->assertSame(
            5,
            $feedback->rating
        );

        $this->assertTrue(
            $feedback->hasValidRating()
        );

        $this->assertSame(
            'Excellent service.',
            $feedback->comment
        );
    }

    public function test_feedback_belongs_to_appointment_customer_and_shop(): void
    {
        [$appointment] =
            $this->completedAppointment();

        $feedback =
            Feedback::factory()->create([
                'appointment_id' =>
                    $appointment->id,

                'customer_id' =>
                    $appointment->customer_id,

                'shop_id' =>
                    $appointment->shop_id,
            ]);

        $this->assertSame(
            $appointment->id,
            $feedback
                ->appointment
                ->id
        );

        $this->assertSame(
            $appointment->customer_id,
            $feedback
                ->customer
                ->id
        );

        $this->assertSame(
            $appointment->shop_id,
            $feedback
                ->shop
                ->id
        );
    }

    public function test_completed_appointment_has_one_feedback(): void
    {
        [$appointment] =
            $this->completedAppointment();

        $feedback =
            Feedback::factory()->create([
                'appointment_id' =>
                    $appointment->id,

                'customer_id' =>
                    $appointment->customer_id,

                'shop_id' =>
                    $appointment->shop_id,
            ]);

        $this->assertSame(
            $feedback->id,
            $appointment
                ->fresh()
                ->feedback
                ->id
        );
    }

    public function test_shop_has_many_feedback_records(): void
    {
        [$firstAppointment, $shop] =
            $this->completedAppointment();

        $secondCustomer =
            User::factory()
                ->customer()
                ->create();

        $secondService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $secondAppointment =
            Appointment::factory()
                ->completed()
                ->create([
                    'customer_id' =>
                        $secondCustomer->id,

                    'shop_id' =>
                        $shop->id,

                    'service_id' =>
                        $secondService->id,

                    'service_name' =>
                        $secondService->name,

                    'price_snapshot' =>
                        $secondService->price,

                    'duration_minutes_snapshot' =>
                        $secondService
                            ->duration_minutes,
                ]);

        Feedback::factory()->create([
            'appointment_id' =>
                $firstAppointment->id,

            'customer_id' =>
                $firstAppointment
                    ->customer_id,

            'shop_id' =>
                $shop->id,

            'rating' =>
                5,
        ]);

        Feedback::factory()->create([
            'appointment_id' =>
                $secondAppointment->id,

            'customer_id' =>
                $secondCustomer->id,

            'shop_id' =>
                $shop->id,

            'rating' =>
                4,
        ]);

        $this->assertCount(
            2,
            $shop->fresh()->feedback
        );
    }

    public function test_appointment_cannot_have_duplicate_feedback(): void
    {
        [$appointment] =
            $this->completedAppointment();

        Feedback::factory()->create([
            'appointment_id' =>
                $appointment->id,

            'customer_id' =>
                $appointment->customer_id,

            'shop_id' =>
                $appointment->shop_id,
        ]);

        $this->expectException(
            QueryException::class
        );

        Feedback::factory()->create([
            'appointment_id' =>
                $appointment->id,

            'customer_id' =>
                $appointment->customer_id,

            'shop_id' =>
                $appointment->shop_id,
        ]);
    }

    public function test_sensitive_feedback_ownership_fields_cannot_be_mass_assigned(): void
    {
        $feedback =
            new Feedback();

        $feedback->fill([
            'appointment_id' =>
                999,

            'customer_id' =>
                999,

            'shop_id' =>
                999,

            'rating' =>
                4,

            'comment' =>
                'Valid review content.',
        ]);

        $this->assertNull(
            $feedback->appointment_id
        );

        $this->assertNull(
            $feedback->customer_id
        );

        $this->assertNull(
            $feedback->shop_id
        );

        $this->assertSame(
            4,
            $feedback->rating
        );

        $this->assertSame(
            'Valid review content.',
            $feedback->comment
        );
    }

    public function test_rating_helper_accepts_values_from_one_to_five(): void
    {
        foreach (
            [1, 2, 3, 4, 5]
            as $rating
        ) {
            $feedback =
                new Feedback([
                    'rating' =>
                        $rating,
                ]);

            $this->assertTrue(
                $feedback
                    ->hasValidRating()
            );
        }
    }

    public function test_rating_helper_rejects_values_outside_one_to_five(): void
    {
        foreach (
            [0, 6, 10]
            as $rating
        ) {
            $feedback =
                new Feedback([
                    'rating' =>
                        $rating,
                ]);

            $this->assertFalse(
                $feedback
                    ->hasValidRating()
            );
        }
    }

    public function test_feedback_comment_can_be_null(): void
    {
        [$appointment] =
            $this->completedAppointment();

        $feedback =
            Feedback::factory()
                ->ratingOnly()
                ->create([
                    'appointment_id' =>
                        $appointment->id,

                    'customer_id' =>
                        $appointment
                            ->customer_id,

                    'shop_id' =>
                        $appointment
                            ->shop_id,

                    'rating' =>
                        5,
                ]);

        $this->assertNull(
            $feedback->comment
        );
    }

    /**
     * @return array{0: Appointment, 1: Shop}
     */
    private function completedAppointment(): array
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Premium Haircut',

                    'price' =>
                        '500.00',

                    'duration_minutes' =>
                        60,
                ]);

        $appointment =
            Appointment::factory()
                ->completed()
                ->create([
                    'customer_id' =>
                        $customer->id,

                    'shop_id' =>
                        $shop->id,

                    'service_id' =>
                        $service->id,

                    'appointment_date' =>
                        now()
                            ->subDay()
                            ->toDateString(),

                    'starts_at' =>
                        '10:00:00',

                    'ends_at' =>
                        '11:00:00',

                    'service_name' =>
                        $service->name,

                    'price_snapshot' =>
                        $service->price,

                    'duration_minutes_snapshot' =>
                        60,

                    'status' =>
                        AppointmentStatus::Completed,

                    'completed_at' =>
                        now()
                            ->subDay()
                            ->setTime(
                                11,
                                0
                            ),
                ]);

        return [
            $appointment,
            $shop,
        ];
    }
}