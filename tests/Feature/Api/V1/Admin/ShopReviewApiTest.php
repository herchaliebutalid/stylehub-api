<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_shop_list_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/shops/pending'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_pending_shop_list(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops/pending'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_pending_shop_list(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/shops/pending'
            )
            ->assertForbidden();
    }

    public function test_admin_can_list_only_pending_shops(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $pendingShop = Shop::factory()->create();

        Shop::factory()
            ->approved()
            ->create();

        Shop::factory()
            ->rejected()
            ->create();

        $response = $this->actingAs(
            $admin,
            'sanctum'
        )->getJson(
            '/api/v1/admin/shops/pending'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                1,
                'data.shops'
            )
            ->assertJsonPath(
                'data.shops.0.id',
                $pendingShop->id
            )
            ->assertJsonPath(
                'data.shops.0.approval_status',
                ShopApprovalStatus::Pending->value
            );

        $this->assertNotNull(
            $response->json(
                'data.shops.0.owner'
            )
        );
    }

    public function test_admin_can_approve_pending_shop(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $shop = Shop::factory()->create();

        $response = $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/approve"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Approved->value
            )
            ->assertJsonPath(
                'data.shop.availability_status',
                ShopAvailabilityStatus::Closed->value
            )
            ->assertJsonPath(
                'data.shop.reviewer.id',
                $admin->id
            );

        $shop->refresh();

        $this->assertSame(
            ShopApprovalStatus::Approved,
            $shop->approval_status
        );

        $this->assertSame(
            ShopAvailabilityStatus::Closed,
            $shop->availability_status
        );

        $this->assertSame(
            $admin->id,
            $shop->reviewed_by
        );

        $this->assertNotNull(
            $shop->reviewed_at
        );

        $this->assertNull(
            $shop->rejection_reason
        );
    }

    public function test_admin_can_reject_pending_shop_with_reason(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $shop = Shop::factory()->create();

        $reason =
            'The submitted business information could not be verified.';

        $response = $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/reject",
            [
                'reason' => $reason,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.shop.approval_status',
                ShopApprovalStatus::Rejected->value
            )
            ->assertJsonPath(
                'data.shop.rejection_reason',
                $reason
            )
            ->assertJsonPath(
                'data.shop.reviewer.id',
                $admin->id
            );

        $shop->refresh();

        $this->assertSame(
            ShopApprovalStatus::Rejected,
            $shop->approval_status
        );

        $this->assertSame(
            ShopAvailabilityStatus::Closed,
            $shop->availability_status
        );

        $this->assertSame(
            $admin->id,
            $shop->reviewed_by
        );

        $this->assertNotNull(
            $shop->reviewed_at
        );

        $this->assertSame(
            $reason,
            $shop->rejection_reason
        );
    }

    public function test_rejection_requires_meaningful_reason(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $shop = Shop::factory()->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/reject",
            [
                'reason' => 'bad',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        $shop->refresh();

        $this->assertSame(
            ShopApprovalStatus::Pending,
            $shop->approval_status
        );

        $this->assertNull(
            $shop->reviewed_by
        );
    }

    public function test_customer_cannot_approve_shop(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $shop = Shop::factory()->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/approve"
        )->assertForbidden();

        $this->assertSame(
            ShopApprovalStatus::Pending,
            $shop->fresh()->approval_status
        );
    }

    public function test_shop_owner_cannot_reject_shop(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/reject",
            [
                'reason' =>
                    'This should never be accepted.',
            ]
        )->assertForbidden();

        $this->assertSame(
            ShopApprovalStatus::Pending,
            $shop->fresh()->approval_status
        );
    }

    public function test_approved_shop_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create();

        $originalReviewer =
            $shop->reviewed_by;

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/reject",
            [
                'reason' =>
                    'Attempting to review an already approved shop.',
            ]
        )
            ->assertStatus(409);

        $shop->refresh();

        $this->assertSame(
            ShopApprovalStatus::Approved,
            $shop->approval_status
        );

        $this->assertSame(
            $originalReviewer,
            $shop->reviewed_by
        );
    }

    public function test_rejected_shop_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $shop = Shop::factory()
            ->rejected()
            ->create();

        $originalReviewer =
            $shop->reviewed_by;

        $originalReason =
            $shop->rejection_reason;

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/approve"
        )
            ->assertStatus(409);

        $shop->refresh();

        $this->assertSame(
            ShopApprovalStatus::Rejected,
            $shop->approval_status
        );

        $this->assertSame(
            $originalReviewer,
            $shop->reviewed_by
        );

        $this->assertSame(
            $originalReason,
            $shop->rejection_reason
        );
    }

    public function test_suspended_admin_cannot_review_shops(): void
    {
        $admin = User::factory()
            ->admin()
            ->suspended()
            ->create();

        $shop = Shop::factory()->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->postJson(
            "/api/v1/admin/shops/{$shop->id}/approve"
        )->assertForbidden();

        $this->assertSame(
            ShopApprovalStatus::Pending,
            $shop->fresh()->approval_status
        );
    }
}