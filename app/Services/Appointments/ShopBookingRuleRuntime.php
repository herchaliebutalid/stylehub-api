<?php

namespace App\Services\Appointments;

use App\Enums\ShopBookingRuleOperation;
use App\Models\Shop;
use Closure;

class ShopBookingRuleRuntime
{
    private ?int $shopId =
        null;

    private ?ShopBookingRuleOperation $operation =
        null;

    public function __construct(
        private readonly ShopBookingRuleResolver $resolver
    ) {}

    public function run(
        Shop $shop,
        ShopBookingRuleOperation $operation,
        Closure $callback
    ): mixed {
        $previousShopId =
            $this->shopId;

        $previousOperation =
            $this->operation;

        /*
        |--------------------------------------------------------------------------
        | Save Current Configuration
        |--------------------------------------------------------------------------
        |
        | The existing availability / booking engine already reads these
        | configuration values. We temporarily replace them with this shop's
        | effective rules, then always restore them in finally.
        |
        */

        $previousConfiguration = [
            'appointments.slot_interval_minutes' =>
                config(
                    'appointments.slot_interval_minutes'
                ),

            'appointments.minimum_lead_time_minutes' =>
                config(
                    'appointments.minimum_lead_time_minutes'
                ),

            'appointments.booking_window_days' =>
                config(
                    'appointments.booking_window_days'
                ),
        ];

        $rules =
            $this->resolver->forShop(
                $shop
            );

        $this->shopId =
            $shop->id;

        $this->operation =
            $operation;

        config([
            'appointments.slot_interval_minutes' =>
                $rules->slot_interval_minutes,

            'appointments.minimum_lead_time_minutes' =>
                $rules->minimum_lead_time_minutes,

            'appointments.booking_window_days' =>
                $rules->booking_window_days,
        ]);

        try {
            return $callback();
        } finally {
            /*
             * Never allow one shop's rules to leak into another request.
             */
            config(
                $previousConfiguration
            );

            $this->shopId =
                $previousShopId;

            $this->operation =
                $previousOperation;
        }
    }

    public function matches(
        int $shopId,
        ShopBookingRuleOperation $operation
    ): bool {
        return $this->shopId
            === $shopId
            &&
            $this->operation
            === $operation;
    }
}