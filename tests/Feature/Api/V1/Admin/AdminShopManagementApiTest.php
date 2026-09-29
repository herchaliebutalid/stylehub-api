<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_management_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/shops'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_shop_management(): void
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
                '/api/v1/admin/shops'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_admin_shop_management(): void
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
                '/api/v1/admin/shops'
            )
            ->assertForbidden();
    }

    public function test_admin_can_browse_shops_across_all_approval_states(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            function (): void {
                Shop::factory()
                    ->create([
                        'approval_status' =>
                            ShopApprovalStatus::Pending,
                    ]);

                Shop::factory()
                    ->approved()
                    ->create();

                Shop::factory()
                    ->create([
                        'approval_status' =>
                            ShopApprovalStatus::Rejected,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops'
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                3,
                'data.shops'
            )
            ->assertJsonPath(
                'data.pagination.total',
                3
            );
    }

    public function test_admin_can_filter_shops_by_approval_status(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            function (): void {
                Shop::factory()
                    ->create([
                        'approval_status' =>
                            ShopApprovalStatus::Pending,
                    ]);

                Shop::factory()
                    ->approved()
                    ->count(2)
                    ->create();
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?approval_status=approved'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.shops'
            );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?approval_status=pending'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            );
    }

    public function test_admin_can_search_shops(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            function (): void {
                Shop::factory()
                    ->approved()
                    ->create([
                        'name' =>
                            'Downtown Barber Studio',
                    ]);

                Shop::factory()
                    ->approved()
                    ->create([
                        'name' =>
                            'Elegant Beauty Lounge',
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?search=Downtown'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.name',
                'Downtown Barber Studio'
            );
    }

    public function test_admin_can_filter_by_shop_type(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $types =
            ShopType::cases();

        $this->assertGreaterThanOrEqual(
            2,
            count(
                $types
            )
        );

        $firstType =
            $types[0];

        $secondType =
            $types[1];

        Shop::withoutEvents(
            function () use (
                $firstType,
                $secondType
            ): void {
                Shop::factory()
                    ->approved()
                    ->create([
                        'type' =>
                            $firstType,
                    ]);

                Shop::factory()
                    ->approved()
                    ->create([
                        'type' =>
                            $secondType,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?type='
                .$firstType->value
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.type',
                $firstType->value
            );
    }

    public function test_admin_can_filter_by_availability(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            function (): void {
                Shop::factory()
                    ->approved()
                    ->create([
                        'availability_status' =>
                            ShopAvailabilityStatus::Open,
                    ]);

                Shop::factory()
                    ->approved()
                    ->create([
                        'availability_status' =>
                            ShopAvailabilityStatus::Closed,
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?availability_status=open'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.availability_status',
                ShopAvailabilityStatus::Open->value
            );
    }

    public function test_admin_can_filter_by_location(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            function (): void {
                Shop::factory()
                    ->approved()
                    ->create([
                        'city' =>
                            'Surigao City',

                        'province' =>
                            'Surigao del Norte',
                    ]);

                Shop::factory()
                    ->approved()
                    ->create([
                        'city' =>
                            'Butuan City',

                        'province' =>
                            'Agusan del Norte',
                    ]);
            }
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?city=Surigao'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.city',
                'Surigao City'
            );
    }

    public function test_admin_shop_list_is_paginated(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::withoutEvents(
            fn () =>
                Shop::factory()
                    ->approved()
                    ->count(3)
                    ->create()
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.shops'
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

    public function test_excessive_page_size_is_rejected(): void
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
                '/api/v1/admin/shops?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_unknown_query_parameter_is_rejected(): void
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
                '/api/v1/admin/shops?password=test'
            )
            ->assertUnprocessable();
    }

    public function test_admin_can_view_one_shop(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->create([
                    'first_name' =>
                        'Maria',

                    'middle_name' =>
                        'Santos',

                    'last_name' =>
                        'Reyes',

                    'email' =>
                        'owner-detail@example.com',

                    'phone_number' =>
                        '+639171234567',
                ]);

        $shop =
            Shop::factory()
                ->for(
                    $owner,
                    'owner'
                )
                ->approved()
                ->create([
                    'name' =>
                        'StyleHub Detail Shop',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.name',
                'StyleHub Detail Shop'
            )
            ->assertJsonPath(
                'data.shop.owner.id',
                $owner->id
            )
            ->assertJsonPath(
                'data.shop.owner.full_name',
                'Maria Santos Reyes'
            )
            ->assertJsonPath(
                'data.shop.owner.email',
                'owner-detail@example.com'
            )
            ->assertJsonStructure([
                'data' => [
                    'shop' => [
                        'statistics' => [
                            'services',
                            'active_services',
                            'appointments',
                            'feedback',
                            'promos',
                            'active_promos',
                        ],
                    ],
                ],
            ]);
    }

    public function test_admin_can_see_rejected_shop_reason(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::withoutEvents(
                fn () =>
                    Shop::factory()
                        ->create([
                            'approval_status' =>
                                ShopApprovalStatus::Rejected,

                            'rejection_reason' =>
                                'Incomplete business information.',
                        ])
            );

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Rejected->value
            )
            ->assertJsonPath(
                'data.shop.review.rejection_reason',
                'Incomplete business information.'
            );
    }

    public function test_shop_detail_does_not_expose_owner_password_or_tokens(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->create([
                    'email' =>
                        'secure-owner@example.com',

                    'status' =>
                        UserStatus::Active,
                ]);

        $shop =
            Shop::factory()
                ->for(
                    $owner,
                    'owner'
                )
                ->approved()
                ->create();

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                "/api/v1/admin/shops/{$shop->id}"
            );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.shop.owner.password'
            )
            ->assertJsonMissingPath(
                'data.shop.owner.remember_token'
            );

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
    }

    public function test_nonexistent_shop_returns_not_found(): void
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
                '/api/v1/admin/shops/999999'
            )
            ->assertNotFound();
    }
}