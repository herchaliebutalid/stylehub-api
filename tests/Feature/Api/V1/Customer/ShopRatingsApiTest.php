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

class ShopRatingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_reviews_require_authentication(): void
    {
        [, $shop] =
            $this->visibleShop();

        $this->getJson(
            "/api/v1/customer/shops/{$shop->id}/reviews"
        )->assertUnauthorized();
    }

    public function test_shop_owner_cannot_use_customer_review_discovery(): void
    {
        [, $shop] =
            $this->visibleShop();

        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_use_customer_review_discovery(): void
    {
        [, $shop] =
            $this->visibleShop();

        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertForbidden();
    }

    public function test_shop_list_contains_verified_average_rating_and_review_count(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $first =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $second =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $third =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $this->feedback(
            $first,
            5
        );

        $this->feedback(
            $second,
            4
        );

        $this->feedback(
            $third,
            3
        );

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shops.0.rating.average',
                4
            )
            ->assertJsonPath(
                'data.shops.0.rating.review_count',
                3
            );
    }

    public function test_shop_without_reviews_has_zero_count_and_null_average(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        $this->visibleShop();

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shops.0.rating.average',
                null
            )
            ->assertJsonPath(
                'data.shops.0.rating.review_count',
                0
            );
    }

    public function test_shop_detail_contains_recent_verified_reviews(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $completed =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $pending =
            $this->appointment(
                $shop,
                AppointmentStatus::Pending
            );

        $this->feedback(
            $completed,
            5,
            'Excellent verified review.'
        );

        /*
         * This invalid historical/database record must never
         * become a verified public review because its appointment
         * is not completed.
         */
        $this->feedback(
            $pending,
            1,
            'This should not be visible.'
        );

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shop.recent_reviews'
            )
            ->assertJsonPath(
                'data.shop.recent_reviews.0.rating',
                5
            )
            ->assertJsonPath(
                'data.shop.recent_reviews.0.comment',
                'Excellent verified review.'
            )
            ->assertJsonPath(
                'data.shop.recent_reviews.0.verified_booking',
                true
            )
            ->assertJsonPath(
                'data.shop.recent_reviews.0.reviewer.label',
                'Verified Customer'
            );
    }

    public function test_customer_can_list_verified_shop_reviews(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $first =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $second =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $this->feedback(
            $first,
            5
        );

        $this->feedback(
            $second,
            4
        );

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.reviews'
            )
            ->assertJsonPath(
                'data.shop.rating.review_count',
                2
            )
            ->assertJsonPath(
                'data.shop.rating.average',
                4.5
            );
    }

    public function test_customer_can_filter_reviews_by_rating(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $fiveStar =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $threeStar =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $this->feedback(
            $fiveStar,
            5,
            'Five stars.'
        );

        $this->feedback(
            $threeStar,
            3,
            'Three stars.'
        );

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews?rating=5"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.reviews'
            )
            ->assertJsonPath(
                'data.reviews.0.rating',
                5
            );
    }

    public function test_unknown_review_query_parameter_is_rejected(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews?customer_id=1"
            )
            ->assertUnprocessable();
    }

    public function test_excessive_review_page_size_is_rejected(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews?per_page=51"
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_pending_shop_reviews_return_not_found(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertNotFound();
    }

    public function test_shop_with_suspended_owner_reviews_return_not_found(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertNotFound();
    }

    public function test_feedback_for_non_completed_appointment_is_not_public(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $appointment =
            $this->appointment(
                $shop,
                AppointmentStatus::Confirmed
            );

        $this->feedback(
            $appointment,
            5
        );

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.reviews'
            )
            ->assertJsonPath(
                'data.shop.rating.review_count',
                0
            );
    }

    public function test_feedback_with_mismatched_customer_is_not_verified(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $appointment =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $wrongCustomer =
            User::factory()
                ->customer()
                ->create();

        $feedback =
            new Feedback([
                'rating' =>
                    5,

                'comment' =>
                    'Invalid ownership record.',
            ]);

        $feedback->appointment_id =
            $appointment->id;

        $feedback->customer_id =
            $wrongCustomer->id;

        $feedback->shop_id =
            $shop->id;

        $feedback->save();

        $this->actingAs(
            $viewer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.reviews'
            )
            ->assertJsonPath(
                'data.shop.rating.review_count',
                0
            );
    }

    public function test_public_review_does_not_expose_customer_account_information(): void
    {
        $viewer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $appointment =
            $this->appointment(
                $shop,
                AppointmentStatus::Completed
            );

        $this->feedback(
            $appointment,
            5,
            'Privacy-safe review.'
        );

        $response =
            $this->actingAs(
                $viewer,
                'sanctum'
            )->getJson(
                "/api/v1/customer/shops/{$shop->id}/reviews"
            );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.reviews.0.customer_id'
            )
            ->assertJsonMissingPath(
                'data.reviews.0.customer'
            )
            ->assertJsonMissingPath(
                'data.reviews.0.email'
            )
            ->assertJsonMissingPath(
                'data.reviews.0.phone_number'
            )
            ->assertJsonMissingPath(
                'data.reviews.0.appointment_id'
            );
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function visibleShop(): array
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        return [
            $owner,
            $shop,
        ];
    }

    private function appointment(
        Shop $shop,
        AppointmentStatus $status
    ): Appointment {
        $customer =
            User::factory()
                ->customer()
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

        return Appointment::factory()
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
    }

    private function feedback(
        Appointment $appointment,
        int $rating,
        ?string $comment = null
    ): Feedback {
        $feedback =
            new Feedback([
                'rating' =>
                    $rating,

                'comment' =>
                    $comment,
            ]);

        $feedback->appointment_id =
            $appointment->id;

        $feedback->customer_id =
            $appointment->customer_id;

        $feedback->shop_id =
            $appointment->shop_id;

        $feedback->save();

        return $feedback;
    }
}