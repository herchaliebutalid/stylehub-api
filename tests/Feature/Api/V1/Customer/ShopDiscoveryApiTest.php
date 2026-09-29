<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\DayOfWeek;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_discovery_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/customer/shops'
        )->assertUnauthorized();
    }

    public function test_shop_owner_cannot_use_customer_shop_discovery(): void
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
                '/api/v1/customer/shops'
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_use_customer_shop_discovery(): void
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
                '/api/v1/customer/shops'
            )
            ->assertForbidden();
    }

    public function test_customer_sees_only_approved_shops_with_active_owners(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $visibleShop] =
            $this->visibleShop([
                'name' =>
                    'Visible Barber Shop',
            ]);

        $pendingOwner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()->create([
            'owner_id' =>
                $pendingOwner->id,

            'name' =>
                'Pending Shop',
        ]);

        $rejectedOwner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()
            ->rejected()
            ->create([
                'owner_id' =>
                    $rejectedOwner->id,

                'name' =>
                    'Rejected Shop',
            ]);

        $suspendedOwner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        Shop::factory()
            ->approved()
            ->create([
                'owner_id' =>
                    $suspendedOwner->id,

                'name' =>
                    'Suspended Owner Shop',
            ]);

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )->getJson(
                '/api/v1/customer/shops'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.id',
                $visibleShop->id
            )
            ->assertJsonPath(
                'data.shops.0.name',
                'Visible Barber Shop'
            );
    }

    public function test_customer_can_search_shops(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->visibleShop([
            'name' =>
                'Downtown Barber',
        ]);

        $this->visibleShop([
            'name' =>
                'Glamour Beauty Lounge',
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops?search=Glamour'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.name',
                'Glamour Beauty Lounge'
            );
    }

    public function test_customer_can_filter_by_shop_type(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->visibleShop([
            'name' =>
                'Classic Barber',

            'type' =>
                ShopType::BarberShop,
        ]);

        $this->visibleShop([
            'name' =>
                'Elegant Beauty',

            'type' =>
                ShopType::BeautyParlor,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops?type='
                .ShopType::BeautyParlor->value
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.name',
                'Elegant Beauty'
            );
    }

    public function test_customer_can_filter_by_location(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->visibleShop([
            'name' =>
                'Surigao Style',

            'city' =>
                'Surigao City',

            'province' =>
                'Surigao del Norte',
        ]);

        $this->visibleShop([
            'name' =>
                'Dapa Style',

            'city' =>
                'Dapa',

            'province' =>
                'Surigao del Norte',
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops?city=Surigao%20City&province=Surigao%20del%20Norte'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.name',
                'Surigao Style'
            );
    }

    public function test_customer_can_filter_by_availability(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->visibleShop([
            'name' =>
                'Open Shop',

            'availability_status' =>
                ShopAvailabilityStatus::Open,
        ]);

        $this->visibleShop([
            'name' =>
                'Closed Shop',

            'availability_status' =>
                ShopAvailabilityStatus::Closed,
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops?availability_status='
                .ShopAvailabilityStatus::Open->value
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.name',
                'Open Shop'
            );
    }

    public function test_shop_list_is_paginated(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->visibleShop([
            'name' => 'Shop A',
        ]);

        $this->visibleShop([
            'name' => 'Shop B',
        ]);

        $this->visibleShop([
            'name' => 'Shop C',
        ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops?per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.shops'
            )
            ->assertJsonPath(
                'data.pagination.per_page',
                2
            )
            ->assertJsonPath(
                'data.pagination.total',
                3
            );
    }

    public function test_customer_cannot_request_excessive_page_size(): void
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
                '/api/v1/customer/shops?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_customer_cannot_filter_by_internal_approval_status(): void
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
                '/api/v1/customer/shops?approval_status=pending'
            )
            ->assertUnprocessable();
    }

    public function test_customer_can_view_approved_shop_detail(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop([
                'name' =>
                    'Premium StyleHub Shop',

                'description' =>
                    'Professional hair and beauty services.',

                'phone_number' =>
                    '+639171234567',

                'email' =>
                    'shop@example.test',

                'address' =>
                    '123 Main Street',

                'city' =>
                    'Surigao City',

                'province' =>
                    'Surigao del Norte',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.name',
                'Premium StyleHub Shop'
            )
            ->assertJsonMissingPath(
                'data.shop.owner_id'
            )
            ->assertJsonMissingPath(
                'data.shop.approval_status'
            )
            ->assertJsonMissingPath(
                'data.shop.rejection_reason'
            )
            ->assertJsonMissingPath(
                'data.shop.reviewed_by'
            );
    }

    public function test_shop_detail_contains_only_active_services(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        Service::factory()->create([
            'shop_id' =>
                $shop->id,

            'name' =>
                'Active Haircut',

            'is_active' =>
                true,
        ]);

        Service::factory()
            ->inactive()
            ->create([
                'shop_id' =>
                    $shop->id,

                'name' =>
                    'Inactive Service',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shop.services'
            )
            ->assertJsonPath(
                'data.shop.services.0.name',
                'Active Haircut'
            );
    }

    public function test_soft_deleted_service_is_not_visible_to_customer(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        Service::factory()->create([
            'shop_id' =>
                $shop->id,

            'name' =>
                'Visible Service',
        ]);

        $deleted =
            Service::factory()->create([
                'shop_id' =>
                    $shop->id,

                'name' =>
                    'Deleted Service',
            ]);

        $deleted->delete();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shop.services'
            )
            ->assertJsonPath(
                'data.shop.services.0.name',
                'Visible Service'
            );
    }

    public function test_shop_schedule_is_returned_in_weekday_order(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Sunday
            )
            ->closed()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        ShopSchedule::factory()
            ->forDay(
                DayOfWeek::Monday
            )
            ->openBetween(
                '09:00:00',
                '18:00:00'
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.schedule.0.day_of_week',
                DayOfWeek::Monday->value
            )
            ->assertJsonPath(
                'data.shop.schedule.1.day_of_week',
                DayOfWeek::Sunday->value
            );
    }

    public function test_pending_shop_detail_returns_not_found(): void
    {
        $customer =
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
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertNotFound();
    }

    public function test_shop_with_suspended_owner_returns_not_found(): void
    {
        $customer =
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
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertNotFound();
    }

    public function test_soft_deleted_shop_is_not_visible(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $shop->delete();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertNotFound();
    }

    /**
     * Create an approved shop whose owner is active.
     *
     * @param array<string, mixed> $shopAttributes
     *
     * @return array{0: User, 1: Shop}
     */
    private function visibleShop(
        array $shopAttributes = []
    ): array {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create(
                    array_merge(
                        [
                            'owner_id' =>
                                $owner->id,

                            'availability_status' =>
                                ShopAvailabilityStatus::Open,

                            'name' =>
                                'StyleHub Shop',

                            'city' =>
                                'Surigao City',

                            'province' =>
                                'Surigao del Norte',
                        ],
                        $shopAttributes
                    )
                );

        return [
            $owner,
            $shop,
        ];
    }
}