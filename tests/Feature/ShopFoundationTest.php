<?php

namespace Tests\Feature;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_factory_creates_pending_closed_barber_shop(): void
    {
        $shop = Shop::factory()->create();

        $this->assertInstanceOf(
            ShopType::class,
            $shop->type
        );

        $this->assertInstanceOf(
            ShopApprovalStatus::class,
            $shop->approval_status
        );

        $this->assertInstanceOf(
            ShopAvailabilityStatus::class,
            $shop->availability_status
        );

        $this->assertSame(
            ShopType::BarberShop,
            $shop->type
        );

        $this->assertSame(
            ShopApprovalStatus::Pending,
            $shop->approval_status
        );

        $this->assertSame(
            ShopAvailabilityStatus::Closed,
            $shop->availability_status
        );

        $this->assertTrue(
            $shop->isPending()
        );

        $this->assertFalse(
            $shop->isApproved()
        );

        $this->assertFalse(
            $shop->isCustomerVisible()
        );
    }

    public function test_shop_belongs_to_shop_owner(): void
    {
        $shop = Shop::factory()->create();

        $this->assertSame(
            UserRole::ShopOwner,
            $shop->owner->role
        );

        $this->assertSame(
            $shop->owner_id,
            $shop->owner->id
        );
    }

    public function test_shop_owner_has_one_shop_relationship(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->assertNotNull(
            $owner->fresh()->shop
        );

        $this->assertSame(
            $shop->id,
            $owner->fresh()->shop->id
        );
    }

    public function test_one_owner_cannot_own_multiple_shops(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->expectException(
            QueryException::class
        );

        Shop::factory()->create([
            'owner_id' => $owner->id,
        ]);
    }

    public function test_sensitive_shop_fields_cannot_be_mass_assigned(): void
    {
        $shop = new Shop();

        $shop->fill([
            'name' => 'Secure Shop',
            'type' => ShopType::BarberShop->value,
            'description' => 'Testing mass assignment.',
            'phone_number' => '+639171234567',
            'email' => 'shop@example.com',
            'address' => 'Example Street',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',

            'owner_id' => 999,
            'approval_status' =>
                ShopApprovalStatus::Approved->value,

            'availability_status' =>
                ShopAvailabilityStatus::Open->value,

            'reviewed_by' => 999,
            'reviewed_at' => now(),
            'rejection_reason' => 'Injected value',
        ]);

        $this->assertNull(
            $shop->owner_id
        );

        $this->assertNull(
            $shop->approval_status
        );

        $this->assertNull(
            $shop->availability_status
        );

        $this->assertNull(
            $shop->reviewed_by
        );

        $this->assertNull(
            $shop->reviewed_at
        );

        $this->assertNull(
            $shop->rejection_reason
        );
    }

    public function test_approved_shop_is_customer_visible(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $this->assertSame(
            ShopApprovalStatus::Approved,
            $shop->approval_status
        );

        $this->assertTrue(
            $shop->isApproved()
        );

        $this->assertTrue(
            $shop->isCustomerVisible()
        );

        $this->assertNotNull(
            $shop->reviewed_by
        );

        $this->assertNotNull(
            $shop->reviewed_at
        );
    }

    public function test_shop_supports_soft_deletion(): void
    {
        $shop = Shop::factory()->create();

        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(Shop::class)
        );

        $shop->delete();

        $this->assertSoftDeleted(
            'shops',
            [
                'id' => $shop->id,
            ]
        );
    }
}