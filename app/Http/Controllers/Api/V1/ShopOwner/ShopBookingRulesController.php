<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\UpdateShopBookingRulesRequest;
use App\Models\Shop;
use App\Models\ShopBookingRule;
use App\Services\Appointments\ShopBookingRuleResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopBookingRulesController extends Controller
{
    public function __construct(
        private readonly ShopBookingRuleResolver $resolver,
        private readonly AuditLogger $audit
    ) {}

    /**
     * Return this authenticated owner's effective booking rules.
     *
     * If no custom row exists yet, the safe application defaults are returned
     * without creating a database record.
     */
    public function show(
        Request $request
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $rules =
            $this->resolver->forShop(
                $shop
            );

        return response()->json([
            'success' =>
                true,

            'data' => [
                'rules' =>
                    $this->rulesData(
                        $rules
                    ),
            ],
        ]);
    }

    /**
     * Create or replace the authenticated shop's booking rules.
     */
    public function update(
        UpdateShopBookingRulesRequest $request
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $validated =
            $request->validated();

        $rules =
            DB::transaction(
                function () use (
                    $shop,
                    $validated
                ): ShopBookingRule {
                    /*
                    |--------------------------------------------------------------------------
                    | Lock Shop First
                    |--------------------------------------------------------------------------
                    |
                    | This serializes simultaneous first-time rule updates and
                    | prevents two requests from racing to create duplicate rows.
                    |
                    */

                    $lockedShop =
                        Shop::query()
                            ->whereKey(
                                $shop->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $rules =
                        ShopBookingRule::query()
                            ->where(
                                'shop_id',
                                $lockedShop->id
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($rules === null) {
                        $rules =
                            new ShopBookingRule();

                        /*
                         * shop_id is deliberately not fillable.
                         * Ownership comes only from the authenticated shop.
                         */
                        $rules->shop_id =
                            $lockedShop->id;
                    }

                    $rules->fill(
                        $validated
                    );

                    $rules->save();

                    return $rules->fresh();
                },
                3
            );

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        |
        | Booking rules directly affect what customers can do, so every
        | successful owner update is recorded against the Shop resource.
        |
        */

        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::ShopBookingRulesUpdated,

                auditable:
                    $shop,

                metadata: [
                    'slot_interval_minutes' =>
                        $rules->slot_interval_minutes,

                    'minimum_lead_time_minutes' =>
                        $rules->minimum_lead_time_minutes,

                    'booking_window_days' =>
                        $rules->booking_window_days,

                    'cancellation_cutoff_minutes' =>
                        $rules->cancellation_cutoff_minutes,

                    'reschedule_cutoff_minutes' =>
                        $rules->reschedule_cutoff_minutes,
                ],
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Booking rules updated successfully.',

            'data' => [
                'rules' =>
                    $this->rulesData(
                        $rules
                    ),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesData(
        ShopBookingRule $rules
    ): array {
        return [
            /*
             * False means the response currently comes from the
             * fallback application defaults.
             */
            'is_customized' =>
                $rules->exists,

            'slot_interval_minutes' =>
                $rules->slot_interval_minutes,

            'minimum_lead_time_minutes' =>
                $rules->minimum_lead_time_minutes,

            'booking_window_days' =>
                $rules->booking_window_days,

            'cancellation_cutoff_minutes' =>
                $rules->cancellation_cutoff_minutes,

            'reschedule_cutoff_minutes' =>
                $rules->reschedule_cutoff_minutes,
        ];
    }

    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request
            ->user()
            ->shop()
            ->firstOrFail();
    }
}