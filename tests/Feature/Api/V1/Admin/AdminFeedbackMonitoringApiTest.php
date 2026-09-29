<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeedbackMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_monitoring_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/feedback'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_feedback_monitoring(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_admin_feedback_monitoring(): void
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
                '/api/v1/admin/feedback'
            )
            ->assertForbidden();
    }

    public function test_admin_can_browse_all_feedback(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            fn () =>
                Feedback::factory()
                    ->count(2)
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                2,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.pagination.total',
                2
            );
    }

    public function test_admin_can_filter_feedback_by_rating(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            function (): void {
                Feedback::factory()
                    ->create([
                        'rating' =>
                            5,
                    ]);

                Feedback::factory()
                    ->create([
                        'rating' =>
                            2,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback?rating=5'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.feedback.0.rating',
                5
            );
    }

    public function test_invalid_feedback_rating_is_rejected(): void
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
                '/api/v1/admin/feedback?rating=6'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'rating',
            ]);
    }

    public function test_admin_can_filter_feedback_by_shop(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $first =
            Feedback::withoutEvents(
                fn () =>
                    Feedback::factory()
                        ->create()
            );

        Feedback::withoutEvents(
            fn () =>
                Feedback::factory()
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback?shop_id='
                .$first->shop_id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.feedback.0.shop.id',
                $first->shop_id
            );
    }

    public function test_admin_can_filter_feedback_by_customer(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $first =
            Feedback::withoutEvents(
                fn () =>
                    Feedback::factory()
                        ->create()
            );

        Feedback::withoutEvents(
            fn () =>
                Feedback::factory()
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback?customer_id='
                .$first->customer_id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.feedback.0.customer.id',
                $first->customer_id
            );
    }

    public function test_admin_can_search_feedback_comment(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            function (): void {
                Feedback::factory()
                    ->create([
                        'comment' =>
                            'Excellent unique haircut experience.',
                    ]);

                Feedback::factory()
                    ->create([
                        'comment' =>
                            'Normal feedback.',
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback'
                .'?search=unique%20haircut'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.feedback.0.comment',
                'Excellent unique haircut experience.'
            );
    }

    public function test_admin_can_filter_feedback_by_submission_date(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            function (): void {
                Feedback::factory()
                    ->create([
                        'created_at' =>
                            '2026-08-10 10:00:00',
                    ]);

                Feedback::factory()
                    ->create([
                        'created_at' =>
                            '2026-08-20 10:00:00',
                    ]);

                Feedback::factory()
                    ->create([
                        'created_at' =>
                            '2026-08-30 10:00:00',
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback'
                .'?date_from=2026-08-15'
                .'&date_to=2026-08-25'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.feedback'
            );
    }

    public function test_invalid_feedback_date_range_is_rejected(): void
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
                '/api/v1/admin/feedback'
                .'?date_from=2026-08-25'
                .'&date_to=2026-08-10'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date_to',
            ]);
    }

    public function test_admin_feedback_list_is_paginated(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Feedback::withoutEvents(
            fn () =>
                Feedback::factory()
                    ->count(3)
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/feedback?per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.feedback'
            )
            ->assertJsonPath(
                'data.pagination.total',
                3
            )
            ->assertJsonPath(
                'data.pagination.per_page',
                2
            );
    }

    public function test_excessive_feedback_page_size_is_rejected(): void
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
                '/api/v1/admin/feedback?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_unknown_feedback_query_parameter_is_rejected(): void
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
                '/api/v1/admin/feedback?password=test'
            )
            ->assertUnprocessable();
    }

    public function test_admin_can_view_one_feedback_record(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $feedback =
            Feedback::withoutEvents(
                fn () =>
                    Feedback::factory()
                        ->create([
                            'rating' =>
                                5,

                            'comment' =>
                                'Excellent service.',
                        ])
            );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/feedback/{$feedback->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.feedback.id',
                $feedback->id
            )
            ->assertJsonPath(
                'data.feedback.rating',
                5
            )
            ->assertJsonPath(
                'data.feedback.comment',
                'Excellent service.'
            )
            ->assertJsonStructure([
                'data' => [
                    'feedback' => [
                        'id',
                        'rating',
                        'comment',
                        'customer',
                        'shop',
                        'appointment',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_nonexistent_feedback_returns_not_found(): void
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
                '/api/v1/admin/feedback/999999'
            )
            ->assertNotFound();
    }

    public function test_admin_feedback_response_does_not_expose_credentials(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $feedback =
            Feedback::withoutEvents(
                fn () =>
                    Feedback::factory()
                        ->create()
            );

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                "/api/v1/admin/feedback/{$feedback->id}"
            );

        $response->assertOk();

        $content =
            strtolower(
                $response->getContent()
            );

        $this->assertStringNotContainsString(
            '"password"',
            $content
        );

        $this->assertStringNotContainsString(
            'remember_token',
            $content
        );

        $this->assertStringNotContainsString(
            'plain_text_token',
            $content
        );

        $this->assertStringNotContainsString(
            'access_token',
            $content
        );
    }
}