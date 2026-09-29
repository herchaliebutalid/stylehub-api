<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/customer/feedback'
        )->assertUnauthorized();
    }

    public function test_shop_owner_cannot_use_customer_feedback_routes(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/feedback'
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_use_customer_feedback_routes(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/feedback'
            )
            ->assertForbidden();
    }

    public function test_customer_can_submit_feedback_for_own_completed_appointment(): void
    {
        [$customer, $appointment, $shop] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        5,

                    'comment' =>
                        'Excellent service and friendly staff.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.feedback.rating',
                5
            )
            ->assertJsonPath(
                'data.feedback.comment',
                'Excellent service and friendly staff.'
            )
            ->assertJsonPath(
                'data.feedback.appointment.id',
                $appointment->id
            )
            ->assertJsonPath(
                'data.feedback.shop.id',
                $shop->id
            );

        $this->assertDatabaseHas(
            'feedback',
            [
                'appointment_id' =>
                    $appointment->id,

                'customer_id' =>
                    $customer->id,

                'shop_id' =>
                    $shop->id,

                'rating' =>
                    5,

                'comment' =>
                    'Excellent service and friendly staff.',
            ]
        );
    }

    public function test_customer_can_submit_rating_without_comment(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        4,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.feedback.rating',
                4
            )
            ->assertJsonPath(
                'data.feedback.comment',
                null
            );
    }

    public function test_feedback_comment_is_trimmed(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/customer/appointments/{$appointment->id}/feedback",
            [
                'rating' =>
                    5,

                'comment' =>
                    '   Very professional service.   ',
            ]
        )->assertCreated();

        $this->assertDatabaseHas(
            'feedback',
            [
                'appointment_id' =>
                    $appointment->id,

                'comment' =>
                    'Very professional service.',
            ]
        );
    }

    public function test_customer_cannot_inject_feedback_ownership_fields(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        5,

                    'customer_id' =>
                        999,

                    'shop_id' =>
                        999,

                    'appointment_id' =>
                        999,
                ]
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'feedback',
            0
        );
    }

    public function test_pending_appointment_cannot_be_reviewed(): void
    {
        [$customer, $appointment] =
            $this->appointment(
                AppointmentStatus::Pending
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        5,
                ]
            )
            ->assertConflict();

        $this->assertDatabaseCount(
            'feedback',
            0
        );
    }

    public function test_confirmed_appointment_cannot_be_reviewed(): void
    {
        [$customer, $appointment] =
            $this->appointment(
                AppointmentStatus::Confirmed
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        5,
                ]
            )
            ->assertConflict();
    }

    public function test_cancelled_appointment_cannot_be_reviewed(): void
    {
        [$customer, $appointment] =
            $this->appointment(
                AppointmentStatus::Cancelled
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        5,
                ]
            )
            ->assertConflict();
    }

    public function test_customer_cannot_review_another_customers_appointment(): void
    {
        [, $appointment] =
            $this->appointment();

        $otherCustomer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $otherCustomer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        5,
                ]
            )
            ->assertNotFound();

        $this->assertDatabaseCount(
            'feedback',
            0
        );
    }

    public function test_appointment_cannot_receive_duplicate_feedback(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        Feedback::factory()->create([
            'appointment_id' =>
                $appointment->id,

            'customer_id' =>
                $customer->id,

            'shop_id' =>
                $appointment->shop_id,

            'rating' =>
                5,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        4,
                ]
            )
            ->assertConflict();

        $this->assertDatabaseCount(
            'feedback',
            1
        );
    }

    public function test_rating_below_one_is_rejected(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        0,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rating',
            ]);
    }

    public function test_rating_above_five_is_rejected(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->postJson(
                "/api/v1/customer/appointments/{$appointment->id}/feedback",
                [
                    'rating' =>
                        6,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rating',
            ]);
    }

    public function test_customer_lists_only_own_feedback(): void
    {
        [$customer, $appointment] =
            $this->appointment();

        [
            $otherCustomer,
            $otherAppointment,
        ] = $this->appointment();

        Feedback::factory()->create([
            'appointment_id' =>
                $appointment->id,

            'customer_id' =>
                $customer->id,

            'shop_id' =>
                $appointment->shop_id,
        ]);

        Feedback::factory()->create([
            'appointment_id' =>
                $otherAppointment->id,

            'customer_id' =>
                $otherCustomer->id,

            'shop_id' =>
                $otherAppointment->shop_id,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/feedback'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.feedback.0.appointment.id',
                $appointment->id
            );
    }

    public function test_customer_cannot_view_another_customers_feedback(): void
    {
        [$customer] =
            $this->appointment();

        [
            $otherCustomer,
            $otherAppointment,
        ] = $this->appointment();

        $feedback =
            Feedback::factory()->create([
                'appointment_id' =>
                    $otherAppointment->id,

                'customer_id' =>
                    $otherCustomer->id,

                'shop_id' =>
                    $otherAppointment->shop_id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/feedback/{$feedback->id}"
            )
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Appointment, 2: Shop, 3: Service}
     */
    private function appointment(
        AppointmentStatus $status =
            AppointmentStatus::Completed
    ): array {
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

                    'is_active' =>
                        true,
                ]);

        $appointment =
            Appointment::factory()
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
                        $status,

                    'completed_at' =>
                        $status
                        === AppointmentStatus::Completed
                            ? now()
                                ->subDay()
                                ->setTime(
                                    11,
                                    0
                                )
                            : null,
                ]);

        return [
            $customer,
            $appointment,
            $shop,
            $service,
        ];
    }
}