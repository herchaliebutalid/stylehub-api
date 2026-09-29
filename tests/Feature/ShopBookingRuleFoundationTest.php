<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopBookingRule;
use App\Services\Appointments\ShopBookingRuleResolver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopBookingRuleFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_rule_table_has_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'shop_booking_rules',
                [
                    'id',
                    'shop_id',
                    'slot_interval_minutes',
                    'minimum_lead_time_minutes',
                    'booking_window_days',
                    'cancellation_cutoff_minutes',
                    'reschedule_cutoff_minutes',
                    'created_at',
                    'updated_at',
                ]
            )
        );
    }

    public function test_booking_rule_belongs_to_shop_and_values_are_cast_to_integers(): void
    {
        $shop =
            Shop::factory()
                ->create();

        $rules =
            new ShopBookingRule([
                'slot_interval_minutes' =>
                    '30',

                'minimum_lead_time_minutes' =>
                    '60',

                'booking_window_days' =>
                    '45',

                'cancellation_cutoff_minutes' =>
                    '120',

                'reschedule_cutoff_minutes' =>
                    '180',
            ]);

        $rules->shop_id =
            $shop->id;

        $rules->save();

        $fresh =
            $rules->fresh();

        $this->assertSame(
            $shop->id,
            $fresh->shop->id
        );

        $this->assertSame(
            30,
            $fresh->slot_interval_minutes
        );

        $this->assertSame(
            60,
            $fresh->minimum_lead_time_minutes
        );

        $this->assertSame(
            45,
            $fresh->booking_window_days
        );

        $this->assertSame(
            120,
            $fresh->cancellation_cutoff_minutes
        );

        $this->assertSame(
            180,
            $fresh->reschedule_cutoff_minutes
        );
    }

    public function test_shop_id_cannot_be_mass_assigned(): void
    {
        $rules =
            new ShopBookingRule();

        $rules->fill([
            'shop_id' =>
                999,

            'slot_interval_minutes' =>
                30,
        ]);

        $this->assertNull(
            $rules->shop_id
        );

        $this->assertSame(
            30,
            $rules->slot_interval_minutes
        );
    }

    public function test_shop_cannot_have_multiple_booking_rule_rows(): void
    {
        $shop =
            Shop::factory()
                ->create();

        $first =
            new ShopBookingRule([
                'slot_interval_minutes' =>
                    15,
            ]);

        $first->shop_id =
            $shop->id;

        $first->save();

        $second =
            new ShopBookingRule([
                'slot_interval_minutes' =>
                    30,
            ]);

        $second->shop_id =
            $shop->id;

        $this->expectException(
            QueryException::class
        );

        $second->save();
    }

    public function test_resolver_uses_safe_application_defaults_for_unconfigured_shop(): void
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

        $shop =
            Shop::factory()
                ->create();

        $rules =
            app(
                ShopBookingRuleResolver::class
            )->forShop(
                $shop
            );

        $this->assertFalse(
            $rules->exists
        );

        $this->assertSame(
            $shop->id,
            $rules->shop_id
        );

        $this->assertSame(
            15,
            $rules->slot_interval_minutes
        );

        $this->assertSame(
            30,
            $rules->minimum_lead_time_minutes
        );

        $this->assertSame(
            60,
            $rules->booking_window_days
        );

        $this->assertSame(
            120,
            $rules->cancellation_cutoff_minutes
        );

        $this->assertSame(
            180,
            $rules->reschedule_cutoff_minutes
        );
    }

    public function test_resolver_prefers_persisted_shop_rules_over_global_defaults(): void
    {
        config([
            'appointments.slot_interval_minutes' =>
                15,

            'appointments.minimum_lead_time_minutes' =>
                0,

            'appointments.booking_window_days' =>
                60,

            'appointments.cancellation_cutoff_minutes' =>
                0,

            'appointments.reschedule_cutoff_minutes' =>
                0,
        ]);

        $shop =
            Shop::factory()
                ->create();

        $stored =
            new ShopBookingRule([
                'slot_interval_minutes' =>
                    30,

                'minimum_lead_time_minutes' =>
                    90,

                'booking_window_days' =>
                    21,

                'cancellation_cutoff_minutes' =>
                    240,

                'reschedule_cutoff_minutes' =>
                    360,
            ]);

        $stored->shop_id =
            $shop->id;

        $stored->save();

        $resolved =
            app(
                ShopBookingRuleResolver::class
            )->forShop(
                $shop
            );

        $this->assertTrue(
            $resolved->exists
        );

        $this->assertSame(
            $stored->id,
            $resolved->id
        );

        $this->assertSame(
            30,
            $resolved->slot_interval_minutes
        );

        $this->assertSame(
            90,
            $resolved->minimum_lead_time_minutes
        );

        $this->assertSame(
            21,
            $resolved->booking_window_days
        );

        $this->assertSame(
            240,
            $resolved->cancellation_cutoff_minutes
        );

        $this->assertSame(
            360,
            $resolved->reschedule_cutoff_minutes
        );
    }
}