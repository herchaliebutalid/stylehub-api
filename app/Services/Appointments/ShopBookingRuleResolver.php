<?php

namespace App\Services\Appointments;

use App\Models\Shop;
use App\Models\ShopBookingRule;

class ShopBookingRuleResolver
{
    /**
     * Resolve persisted rules for a shop or return an unsaved model carrying
     * the current application defaults when the shop has not configured them.
     */
    public function forShop(
        Shop $shop
    ): ShopBookingRule {
        $stored =
            ShopBookingRule::query()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->first();

        if ($stored !== null) {
            return $stored;
        }

        $rules =
            new ShopBookingRule(
                $this->defaults()
            );

        /*
         * shop_id is deliberately not mass assignable. The server attaches
         * ownership directly from the authenticated/resolved shop.
         */
        $rules->shop_id =
            $shop->id;

        $rules->setRelation(
            'shop',
            $shop
        );

        return $rules;
    }

    /**
     * @return array{
     *     slot_interval_minutes: int,
     *     minimum_lead_time_minutes: int,
     *     booking_window_days: int,
     *     cancellation_cutoff_minutes: int,
     *     reschedule_cutoff_minutes: int
     * }
     */
    public function defaults(): array
    {
        return [
            'slot_interval_minutes' =>
                $this->clamp(
                    (int) config(
                        'appointments.slot_interval_minutes',
                        15
                    ),
                    ShopBookingRule::MIN_SLOT_INTERVAL_MINUTES,
                    ShopBookingRule::MAX_SLOT_INTERVAL_MINUTES
                ),

            'minimum_lead_time_minutes' =>
                $this->clamp(
                    (int) config(
                        'appointments.minimum_lead_time_minutes',
                        0
                    ),
                    ShopBookingRule::MIN_LEAD_TIME_MINUTES,
                    ShopBookingRule::MAX_LEAD_TIME_MINUTES
                ),

            'booking_window_days' =>
                $this->clamp(
                    (int) config(
                        'appointments.booking_window_days',
                        60
                    ),
                    ShopBookingRule::MIN_BOOKING_WINDOW_DAYS,
                    ShopBookingRule::MAX_BOOKING_WINDOW_DAYS
                ),

            'cancellation_cutoff_minutes' =>
                $this->clamp(
                    (int) config(
                        'appointments.cancellation_cutoff_minutes',
                        0
                    ),
                    ShopBookingRule::MIN_CUTOFF_MINUTES,
                    ShopBookingRule::MAX_CUTOFF_MINUTES
                ),

            'reschedule_cutoff_minutes' =>
                $this->clamp(
                    (int) config(
                        'appointments.reschedule_cutoff_minutes',
                        0
                    ),
                    ShopBookingRule::MIN_CUTOFF_MINUTES,
                    ShopBookingRule::MAX_CUTOFF_MINUTES
                ),
        ];
    }

    private function clamp(
        int $value,
        int $minimum,
        int $maximum
    ): int {
        return max(
            $minimum,
            min(
                $maximum,
                $value
            )
        );
    }
}