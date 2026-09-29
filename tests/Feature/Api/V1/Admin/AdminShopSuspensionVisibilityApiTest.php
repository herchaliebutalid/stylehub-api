<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ShopAdministrativeStatus;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopSuspensionVisibilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_shop_list_exposes_independent_administrative_state_and_suspension_summary(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $activeShop =
            Shop::factory()
                ->approved()
                ->create([
                    'name' =>
                        'Active Style Shop',
                ]);

        $suspendedShop =
            Shop::factory()
                ->approved()
                ->suspended(
                    'Repeated customer safety complaints.'
                )
                ->create([
                    'name' =>
                        'Suspended Style Shop',
                ]);

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/admin/shops?per_page=50'
                )
                ->assertOk();

        $shops =
            collect(
                $response->json(
                    'data.shops'
                )
            )
                ->keyBy(
                    'id'
                );

        $this->assertSame(
            ShopAdministrativeStatus::Active->value,
            $shops[$activeShop->id][
                'administrative_status'
            ]
        );

        $this->assertFalse(
            $shops[$activeShop->id][
                'suspension'
            ][
                'is_suspended'
            ]
        );

        $this->assertNull(
            $shops[$activeShop->id][
                'suspension'
            ][
                'suspended_at'
            ]
        );

        $this->assertNull(
            $shops[$activeShop->id][
                'suspension'
            ][
                'reason'
            ]
        );

        $this->assertSame(
            ShopAdministrativeStatus::Suspended->value,
            $shops[$suspendedShop->id][
                'administrative_status'
            ]
        );

        $this->assertTrue(
            $shops[$suspendedShop->id][
                'suspension'
            ][
                'is_suspended'
            ]
        );

        $this->assertNotNull(
            $shops[$suspendedShop->id][
                'suspension'
            ][
                'suspended_at'
            ]
        );

        $this->assertSame(
            'Repeated customer safety complaints.',
            $shops[$suspendedShop->id][
                'suspension'
            ][
                'reason'
            ]
        );
    }

    public function test_admin_can_filter_shops_by_administrative_status(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::factory()
            ->approved()
            ->count(2)
            ->create();

        $suspendedShop =
            Shop::factory()
                ->approved()
                ->suspended()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?administrative_status=active'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.shops'
            );

        $this->getJson(
            '/api/v1/admin/shops?administrative_status=suspended'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.id',
                $suspendedShop->id
            )
            ->assertJsonPath(
                'data.shops.0.administrative_status',
                ShopAdministrativeStatus::Suspended
                    ->value
            );
    }

    public function test_invalid_administrative_status_filter_is_rejected(): void
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
                '/api/v1/admin/shops?administrative_status=disabled'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'administrative_status',
            ]);
    }

    public function test_suspended_shop_detail_exposes_safe_current_suspension_information(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $suspendingAdmin =
            User::factory()
                ->admin()
                ->create([
                    'first_name' =>
                        'Andrea',

                    'middle_name' =>
                        null,

                    'last_name' =>
                        'Santos',
                ]);

        $owner =
            User::factory()
                ->shopOwner()
                ->create([
                    'status' =>
                        UserStatus::Active,
                ]);

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $shop->forceFill([
            'administrative_status' =>
                ShopAdministrativeStatus::Suspended,

            'suspended_by' =>
                $suspendingAdmin->id,

            'suspended_at' =>
                now(),

            'suspension_reason' =>
                'Repeated violation of marketplace safety policy.',
        ])->save();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.administrative_status',
                ShopAdministrativeStatus::Suspended
                    ->value
            )
            ->assertJsonPath(
                'data.shop.suspension.is_suspended',
                true
            )
            ->assertJsonPath(
                'data.shop.suspension.suspended_by',
                $suspendingAdmin->id
            )
            ->assertJsonPath(
                'data.shop.suspension.suspender.id',
                $suspendingAdmin->id
            )
            ->assertJsonPath(
                'data.shop.suspension.suspender.full_name',
                'Andrea Santos'
            )
            ->assertJsonPath(
                'data.shop.suspension.reason',
                'Repeated violation of marketplace safety policy.'
            )
            ->assertJsonPath(
                'data.shop.owner.status',
                UserStatus::Active
                    ->value
            )
            ->assertJsonMissingPath(
                'data.shop.suspension.suspender.email'
            )
            ->assertJsonMissingPath(
                'data.shop.suspension.suspender.password'
            );
    }

    public function test_active_shop_detail_returns_explicit_empty_suspension_state(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.administrative_status',
                ShopAdministrativeStatus::Active
                    ->value
            )
            ->assertJsonPath(
                'data.shop.suspension.is_suspended',
                false
            )
            ->assertJsonPath(
                'data.shop.suspension.suspended_by',
                null
            )
            ->assertJsonPath(
                'data.shop.suspension.suspender',
                null
            )
            ->assertJsonPath(
                'data.shop.suspension.suspended_at',
                null
            )
            ->assertJsonPath(
                'data.shop.suspension.reason',
                null
            );
    }

    public function test_administrative_status_filter_combines_with_existing_approval_filter(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        Shop::factory()
            ->approved()
            ->suspended()
            ->create();

        Shop::factory()
            ->approved()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops?approval_status=approved&administrative_status=suspended'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.approval_status',
                'approved'
            )
            ->assertJsonPath(
                'data.shops.0.administrative_status',
                'suspended'
            );
    }
}