<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\ShopBookingRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopBookingRuleManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_rule_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/booking-rules'
        )->assertUnauthorized();

        $this->putJson(
            '/api/v1/shop-owner/booking-rules',
            $this->validPayload()
        )->assertUnauthorized();
    }

    public function test_customer_and_admin_cannot_manage_shop_booking_rules(): void
    {
        foreach (
            [
                User::factory()
                    ->customer()
                    ->create(),

                User::factory()
                    ->admin()
                    ->create(),
            ] as $user
        ) {
            $this->actingAs(
                $user,
                'sanctum'
            )
                ->getJson(
                    '/api/v1/shop-owner/booking-rules'
                )
                ->assertForbidden();
        }
    }

    public function test_pending_shop_owner_cannot_manage_booking_rules(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()
            ->create([
                'owner_id' =>
                    $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/booking-rules'
            )
            ->assertForbidden();
    }

    public function test_unconfigured_shop_receives_safe_default_rules(): void
    {
        config([
            'appointments.slot_interval_minutes' =>
                15,

            'appointments.minimum_lead_time_minutes' =>
                30,

            'appointments.booking_window_days' =>
                60,

            'appointments.cancellation_cutoff_minutes' =>
                120,

            'appointments.reschedule_cutoff_minutes' =>
                180,
        ]);

        [$owner] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/booking-rules'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.rules.is_customized',
                false
            )
            ->assertJsonPath(
                'data.rules.slot_interval_minutes',
                15
            )
            ->assertJsonPath(
                'data.rules.minimum_lead_time_minutes',
                30
            )
            ->assertJsonPath(
                'data.rules.booking_window_days',
                60
            )
            ->assertJsonPath(
                'data.rules.cancellation_cutoff_minutes',
                120
            )
            ->assertJsonPath(
                'data.rules.reschedule_cutoff_minutes',
                180
            );

        /*
         * Reading defaults must not silently create data.
         */
        $this->assertDatabaseCount(
            'shop_booking_rules',
            0
        );
    }

    public function test_approved_owner_can_create_and_update_own_booking_rules(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validPayload();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->putJson(
                '/api/v1/shop-owner/booking-rules',
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.rules.is_customized',
                true
            )
            ->assertJsonPath(
                'data.rules.slot_interval_minutes',
                30
            )
            ->assertJsonPath(
                'data.rules.booking_window_days',
                45
            );

        $this->assertDatabaseHas(
            'shop_booking_rules',
            [
                'shop_id' =>
                    $shop->id,

                'slot_interval_minutes' =>
                    30,

                'minimum_lead_time_minutes' =>
                    60,

                'booking_window_days' =>
                    45,

                'cancellation_cutoff_minutes' =>
                    120,

                'reschedule_cutoff_minutes' =>
                    180,
            ]
        );

        /*
         * PUT replaces the existing shop rule values rather
         * than inserting another row.
         */
        $updatedPayload = [
            ...$payload,

            'slot_interval_minutes' =>
                20,

            'booking_window_days' =>
                21,
        ];

        $this->putJson(
            '/api/v1/shop-owner/booking-rules',
            $updatedPayload
        )
            ->assertOk()
            ->assertJsonPath(
                'data.rules.slot_interval_minutes',
                20
            )
            ->assertJsonPath(
                'data.rules.booking_window_days',
                21
            );

        $this->assertSame(
            1,
            ShopBookingRule::query()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->count()
        );
    }

    public function test_owner_update_never_modifies_another_shops_rules(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        [, $otherShop] =
            $this->approvedOwnerAndShop();

        $otherRules =
            new ShopBookingRule(
                $this->validPayload()
            );

        $otherRules->shop_id =
            $otherShop->id;

        $otherRules->save();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->putJson(
                '/api/v1/shop-owner/booking-rules',
                [
                    ...$this->validPayload(),

                    'slot_interval_minutes' =>
                        10,
                ]
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'shop_booking_rules',
            [
                'shop_id' =>
                    $shop->id,

                'slot_interval_minutes' =>
                    10,
            ]
        );

        $this->assertSame(
            30,
            $otherRules
                ->fresh()
                ->slot_interval_minutes
        );
    }

    public function test_invalid_or_client_controlled_booking_rule_fields_are_rejected(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        );

        /*
         * Unsupported slot interval.
         */
        $this->putJson(
            '/api/v1/shop-owner/booking-rules',
            [
                ...$this->validPayload(),

                'slot_interval_minutes' =>
                    7,
            ]
        )->assertUnprocessable();

        /*
         * Maximum booking window is 365 days.
         */
        $this->putJson(
            '/api/v1/shop-owner/booking-rules',
            [
                ...$this->validPayload(),

                'booking_window_days' =>
                    366,
            ]
        )->assertUnprocessable();

        /*
         * Client must never control shop ownership.
         */
        $this->putJson(
            '/api/v1/shop-owner/booking-rules',
            [
                ...$this->validPayload(),

                'shop_id' =>
                    999,
            ]
        )->assertUnprocessable();
    }

    public function test_booking_rule_update_is_audited_against_the_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->putJson(
                '/api/v1/shop-owner/booking-rules',
                $this->validPayload()
            )
            ->assertOk();

        $log =
            AuditLog::query()
                ->where(
                    'event',
                    AuditEvent::ShopBookingRulesUpdated
                        ->value
                )
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $this->assertSame(
            $owner->id,
            $log->actor_id
        );

        $this->assertSame(
            $shop->getMorphClass(),
            $log->auditable_type
        );

        $this->assertSame(
            $shop->id,
            $log->auditable_id
        );

        $this->assertSame(
            30,
            $log->metadata[
                'slot_interval_minutes'
            ]
        );
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function approvedOwnerAndShop(): array
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

    /**
     * @return array<string, int>
     */
    private function validPayload(): array
    {
        return [
            'slot_interval_minutes' =>
                30,

            'minimum_lead_time_minutes' =>
                60,

            'booking_window_days' =>
                45,

            'cancellation_cutoff_minutes' =>
                120,

            'reschedule_cutoff_minutes' =>
                180,
        ];
    }
}