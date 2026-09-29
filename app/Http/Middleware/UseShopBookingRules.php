<?php

namespace App\Http\Middleware;

use App\Enums\ShopBookingRuleOperation;
use App\Models\Appointment;
use App\Models\Shop;
use App\Services\Appointments\ShopBookingRuleRuntime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseShopBookingRules
{
    public function __construct(
        private readonly ShopBookingRuleRuntime $runtime
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $operation
    ): Response {
        $resolvedOperation =
            ShopBookingRuleOperation::tryFrom(
                $operation
            );

        /*
         * An invalid internal operation parameter should never weaken
         * normal route authorization or validation.
         */
        if ($resolvedOperation === null) {
            return $next(
                $request
            );
        }

        $shop =
            $this->resolveShop(
                $request,
                $resolvedOperation
            );

        /*
         * If the resource cannot be resolved here, leave the normal
         * controller/workflow responsible for its existing 404/422 response.
         *
         * This prevents this middleware from leaking resource existence.
         */
        if ($shop === null) {
            return $next(
                $request
            );
        }

        return $this->runtime->run(
            shop:
                $shop,

            operation:
                $resolvedOperation,

            callback:
                fn (): Response =>
                    $next(
                        $request
                    ),
        );
    }

    private function resolveShop(
        Request $request,
        ShopBookingRuleOperation $operation
    ): ?Shop {
        return match ($operation) {
            /*
             * Availability URLs contain:
             *
             * /shops/{shop}/services/{service}/...
             */
            ShopBookingRuleOperation::Availability =>
                $this->shopFromIdentifier(
                    $request->route(
                        'shop'
                    )
                ),

            /*
             * Booking body contains shop_id, but normal booking validation
             * remains authoritative. An invalid ID is not rejected here.
             */
            ShopBookingRuleOperation::CustomerBooking =>
                $this->shopFromIdentifier(
                    $request->input(
                        'shop_id'
                    )
                ),

            /*
             * For cancellation/reschedule, derive the shop only through
             * an appointment belonging to the authenticated customer.
             */
            ShopBookingRuleOperation::CustomerCancellation,
            ShopBookingRuleOperation::CustomerRescheduleRequest =>
                $this->shopFromOwnedAppointment(
                    $request
                ),

            /*
             * A Shop Owner never supplies shop_id. Their authenticated
             * one-to-one shop relationship determines the rule set.
             */
            ShopBookingRuleOperation::ShopOwnerRescheduleApproval =>
                $request
                    ->user()
                    ?->shop()
                    ->first(),
        };
    }

    private function shopFromOwnedAppointment(
        Request $request
    ): ?Shop {
        $user =
            $request->user();

        if ($user === null) {
            return null;
        }

        $appointmentId =
            $this->positiveInteger(
                $request->route(
                    'appointment'
                )
            );

        if ($appointmentId === null) {
            return null;
        }

        $appointment =
            Appointment::query()
                ->whereKey(
                    $appointmentId
                )
                ->where(
                    'customer_id',
                    $user->id
                )
                ->first();

        if ($appointment === null) {
            return null;
        }

        return Shop::query()
            ->whereKey(
                $appointment->shop_id
            )
            ->first();
    }

    private function shopFromIdentifier(
        mixed $value
    ): ?Shop {
        $shopId =
            $this->positiveInteger(
                $value
            );

        if ($shopId === null) {
            return null;
        }

        return Shop::query()
            ->whereKey(
                $shopId
            )
            ->first();
    }

    private function positiveInteger(
        mixed $value
    ): ?int {
        if (
            ! is_scalar(
                $value
            )
            ||
            ! ctype_digit(
                (string) $value
            )
        ) {
            return null;
        }

        $integer =
            (int) $value;

        return $integer > 0
            ? $integer
            : null;
    }
}