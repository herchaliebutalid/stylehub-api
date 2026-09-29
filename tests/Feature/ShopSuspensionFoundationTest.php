<?php

namespace Tests\Feature;

use App\Enums\ShopAdministrativeStatus;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopSuspensionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shops_table_has_independent_suspension_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'shops',
                [
                    'administrative_status',
                    'suspended_by',
                    'suspended_at',
                    'suspension_reason',
                ]
            )
        );
    }

    public function test_new_shop_defaults_to_administratively_active(): void
    {
        $shop =
            Shop::factory()
                ->create();

        $this->assertInstanceOf(
            ShopAdministrativeStatus::class,
            $shop->administrative_status
        );

        $this->assertSame(
            ShopAdministrativeStatus::Active,
            $shop->administrative_status
        );

        $this->assertTrue(
            $shop->isAdministrativelyActive()
        );

        $this->assertFalse(
            $shop->isSuspended()
        );

        $this->assertNull(
            $shop->suspended_by
        );

        $this->assertNull(
            $shop->suspended_at
        );

        $this->assertNull(
            $shop->suspension_reason
        );
    }

    public function test_suspension_fields_cannot_be_mass_assigned(): void
    {
        $shop =
            new Shop();

        /*
         * administrative_status already defaults to Active at the model
         * level. Client-controlled fill() must not be able to change that
         * trusted default or inject any suspension metadata.
         */

        $shop->fill([
            'name' =>
                'Secure Shop',

            'administrative_status' =>
                ShopAdministrativeStatus::Suspended
                    ->value,

            'suspended_by' =>
                999,

            'suspended_at' =>
                now(),

            'suspension_reason' =>
                'Injected suspension.',
        ]);

        $this->assertSame(
            'Secure Shop',
            $shop->name
        );

        /*
         * The attempted "suspended" value was ignored because
         * administrative_status is not fillable.
         */
        $this->assertSame(
            ShopAdministrativeStatus::Active,
            $shop->administrative_status
        );

        $this->assertFalse(
            $shop->isSuspended()
        );

        $this->assertTrue(
            $shop->isAdministrativelyActive()
        );

        $this->assertNull(
            $shop->suspended_by
        );

        $this->assertNull(
            $shop->suspended_at
        );

        $this->assertNull(
            $shop->suspension_reason
        );
    }

    public function test_suspended_shop_has_admin_relationship_and_timestamp_cast(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->suspended(
                    'Repeated policy violation.'
                )
                ->create();

        $this->assertSame(
            ShopAdministrativeStatus::Suspended,
            $shop->administrative_status
        );

        $this->assertTrue(
            $shop->isSuspended()
        );

        $this->assertFalse(
            $shop->isAdministrativelyActive()
        );

        $this->assertTrue(
            $shop->suspender->isAdmin()
        );

        $this->assertNotNull(
            $shop->suspended_at
        );

        $this->assertSame(
            'Repeated policy violation.',
            $shop->suspension_reason
        );
    }

    public function test_suspending_shop_does_not_suspend_owner_account(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->suspended()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $this->assertTrue(
            $shop->isSuspended()
        );

        $this->assertSame(
            UserStatus::Active,
            $owner->fresh()->status
        );
    }

    public function test_suspended_approved_shop_is_not_customer_visible(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->suspended()
                ->create();

        $this->assertTrue(
            $shop->isApproved()
        );

        $this->assertTrue(
            $shop->isSuspended()
        );

        $this->assertFalse(
            $shop->isCustomerVisible()
        );
    }

    public function test_customer_visibility_scope_excludes_suspended_shop(): void
    {
        $activeShop =
            Shop::factory()
                ->approved()
                ->create();

        $suspendedShop =
            Shop::factory()
                ->approved()
                ->suspended()
                ->create();

        $visibleIds =
            Shop::query()
                ->visibleToCustomers()
                ->pluck(
                    'id'
                )
                ->all();

        $this->assertContains(
            $activeShop->id,
            $visibleIds
        );

        $this->assertNotContains(
            $suspendedShop->id,
            $visibleIds
        );
    }
}