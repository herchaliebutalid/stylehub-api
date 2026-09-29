<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AuditEvent;
use App\Enums\ShopAdministrativeStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopSuspensionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_suspension_routes_require_authentication(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $this->patchJson(
            "/api/v1/admin/shops/{$shop->id}/suspend",
            [
                'reason' =>
                    'Temporary policy investigation.',
            ]
        )->assertUnauthorized();

        $this->patchJson(
            "/api/v1/admin/shops/{$shop->id}/reactivate"
        )->assertUnauthorized();
    }

    public function test_customer_and_shop_owner_cannot_manage_shop_suspension(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        foreach (
            [
                User::factory()
                    ->customer()
                    ->create(),

                User::factory()
                    ->shopOwner()
                    ->create(),
            ] as $user
        ) {
            $this->actingAs(
                $user,
                'sanctum'
            )
                ->patchJson(
                    "/api/v1/admin/shops/{$shop->id}/suspend",
                    [
                        'reason' =>
                            'Unauthorized suspension attempt.',
                    ]
                )
                ->assertForbidden();
        }
    }

    public function test_suspended_admin_cannot_manage_shop_suspension(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->suspended()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/suspend",
                [
                    'reason' =>
                        'This request must never be allowed.',
                ]
            )
            ->assertForbidden();
    }

    public function test_suspension_requires_meaningful_reason_and_rejects_unknown_fields(): void
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
        );

        $this->patchJson(
            "/api/v1/admin/shops/{$shop->id}/suspend",
            [
                'reason' =>
                    'short',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        $this->patchJson(
            "/api/v1/admin/shops/{$shop->id}/suspend",
            [
                'reason' =>
                    'A valid administrative suspension reason.',

                'administrative_status' =>
                    ShopAdministrativeStatus::Suspended
                        ->value,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'administrative_status',
            ]);
    }

    public function test_admin_can_suspend_approved_shop_without_suspending_owner_or_revoking_sessions(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $owner->createToken(
            'owner-device'
        );

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $ownerSessionCount =
            $owner
                ->tokens()
                ->count();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/suspend",
                [
                    'reason' =>
                        '  Repeated violations of shop operating policy.  ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.administrative_status',
                ShopAdministrativeStatus::Suspended
                    ->value
            )
            ->assertJsonPath(
                'data.shop.suspension.reason',
                'Repeated violations of shop operating policy.'
            )
            ->assertJsonPath(
                'data.shop.owner.status',
                UserStatus::Active
                    ->value
            );

        $shop->refresh();
        $owner->refresh();

        $this->assertSame(
            ShopAdministrativeStatus::Suspended,
            $shop->administrative_status
        );

        $this->assertSame(
            $admin->id,
            $shop->suspended_by
        );

        $this->assertNotNull(
            $shop->suspended_at
        );

        $this->assertSame(
            'Repeated violations of shop operating policy.',
            $shop->suspension_reason
        );

        $this->assertSame(
            UserStatus::Active,
            $owner->status
        );

        $this->assertSame(
            $ownerSessionCount,
            $owner
                ->tokens()
                ->count()
        );
    }

    public function test_only_approved_shops_can_be_suspended(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $pendingShop =
            Shop::factory()
                ->create();

        $rejectedShop =
            Shop::withoutEvents(
                fn () =>
                    Shop::factory()
                        ->create([
                            'approval_status' =>
                                ShopApprovalStatus::Rejected,

                            'rejection_reason' =>
                                'Registration was rejected.',
                        ])
            );

        $this->actingAs(
            $admin,
            'sanctum'
        );

        foreach (
            [
                $pendingShop,
                $rejectedShop,
            ] as $shop
        ) {
            $this->patchJson(
                "/api/v1/admin/shops/{$shop->id}/suspend",
                [
                    'reason' =>
                        'Administrative action is not valid here.',
                ]
            )->assertConflict();

            $this->assertSame(
                ShopAdministrativeStatus::Active,
                $shop
                    ->fresh()
                    ->administrative_status
            );
        }
    }

    public function test_duplicate_suspension_returns_conflict_and_preserves_original_suspension(): void
    {
        $firstAdmin =
            User::factory()
                ->admin()
                ->create();

        $secondAdmin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $this->actingAs(
            $firstAdmin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/suspend",
                [
                    'reason' =>
                        'Original administrative suspension reason.',
                ]
            )
            ->assertOk();

        $originalSuspendedAt =
            $shop
                ->fresh()
                ->suspended_at;

        $this->actingAs(
            $secondAdmin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/suspend",
                [
                    'reason' =>
                        'A replacement reason must not overwrite history.',
                ]
            )
            ->assertConflict();

        $shop->refresh();

        $this->assertSame(
            $firstAdmin->id,
            $shop->suspended_by
        );

        $this->assertSame(
            'Original administrative suspension reason.',
            $shop->suspension_reason
        );

        $this->assertTrue(
            $shop
                ->suspended_at
                ->equalTo(
                    $originalSuspendedAt
                )
        );
    }

    public function test_admin_can_reactivate_shop_without_changing_owner_account_state(): void
    {
        $administrator =
            User::factory()
                ->admin()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->suspended(
                    'Temporary administrative suspension.'
                )
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $this->actingAs(
            $administrator,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/reactivate"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.administrative_status',
                ShopAdministrativeStatus::Active
                    ->value
            )
            ->assertJsonPath(
                'data.shop.suspension.suspended_by',
                null
            )
            ->assertJsonPath(
                'data.shop.suspension.suspended_at',
                null
            )
            ->assertJsonPath(
                'data.shop.suspension.reason',
                null
            )
            ->assertJsonPath(
                'data.shop.owner.status',
                UserStatus::Suspended
                    ->value
            );

        $shop->refresh();
        $owner->refresh();

        $this->assertSame(
            ShopAdministrativeStatus::Active,
            $shop->administrative_status
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

        $this->assertSame(
            UserStatus::Suspended,
            $owner->status
        );
    }

    public function test_reactivating_active_shop_returns_conflict_and_rejects_client_controlled_fields(): void
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
        );

        $this->patchJson(
            "/api/v1/admin/shops/{$shop->id}/reactivate",
            [
                'owner_id' =>
                    999,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'owner_id',
            ]);

        $this->patchJson(
            "/api/v1/admin/shops/{$shop->id}/reactivate"
        )->assertConflict();
    }

    public function test_suspend_and_reactivate_actions_are_audited_against_shop(): void
    {
        $suspendingAdmin =
            User::factory()
                ->admin()
                ->create();

        $reactivatingAdmin =
            User::factory()
                ->admin()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $this->actingAs(
            $suspendingAdmin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/suspend",
                [
                    'reason' =>
                        'Repeated administrative policy violation.',
                ]
            )
            ->assertOk();

        $suspensionAudit =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::ShopSuspended
                        ->value
                )
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $this->assertSame(
            $suspendingAdmin->id,
            $suspensionAudit->actor_id
        );

        $this->assertSame(
            $shop->getMorphClass(),
            $suspensionAudit->auditable_type
        );

        $this->assertSame(
            $shop->id,
            $suspensionAudit->auditable_id
        );

        $this->assertSame(
            ShopAdministrativeStatus::Suspended
                ->value,
            $suspensionAudit->metadata[
                'new_status'
            ]
        );

        $this->assertSame(
            'Repeated administrative policy violation.',
            $suspensionAudit->metadata[
                'reason'
            ]
        );

        $this->actingAs(
            $reactivatingAdmin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/shops/{$shop->id}/reactivate"
            )
            ->assertOk();

        $reactivationAudit =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::ShopReactivated
                        ->value
                )
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $this->assertSame(
            $reactivatingAdmin->id,
            $reactivationAudit->actor_id
        );

        $this->assertSame(
            $shop->id,
            $reactivationAudit->auditable_id
        );

        $this->assertSame(
            ShopAdministrativeStatus::Active
                ->value,
            $reactivationAudit->metadata[
                'new_status'
            ]
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
            ->patchJson(
                '/api/v1/admin/shops/999999/suspend',
                [
                    'reason' =>
                        'This target does not exist in the system.',
                ]
            )
            ->assertNotFound();
    }
}