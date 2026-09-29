<?php

namespace App\Providers;

use App\Enums\ShopBookingRuleOperation;
use App\Http\Middleware\UseShopBookingRules;
use App\Models\Appointment;
use App\Models\RescheduleRequest;
use App\Observers\AppointmentBookingRuleObserver;
use App\Observers\RescheduleRequestBookingRuleObserver;
use App\Services\Appointments\ShopBookingRuleRuntime;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ShopBookingRuleEnforcementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Middleware and model observers must share one request-scoped
         * runtime state object.
         */
        $this->app->singleton(
            ShopBookingRuleRuntime::class
        );
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Final Model-Write Guards
        |--------------------------------------------------------------------------
        */

        Appointment::observe(
            AppointmentBookingRuleObserver::class
        );

        RescheduleRequest::observe(
            RescheduleRequestBookingRuleObserver::class
        );

        /*
        |--------------------------------------------------------------------------
        | Existing Route Integration
        |--------------------------------------------------------------------------
        |
        | RouteMatched fires before Laravel gathers/executes the middleware
        | stack. This lets us integrate with your current routes without
        | replacing the large routes/api.php file.
        |
        */

        Event::listen(
            RouteMatched::class,
            function (
                RouteMatched $event
            ): void {
                $operation =
                    $this->operationForRoute(
                        $event
                            ->route
                            ->getName()
                    );

                if ($operation === null) {
                    return;
                }

                $middleware =
                    UseShopBookingRules::class
                    .':'
                    .$operation->value;

                /*
                 * Inspect raw middleware rather than gatherMiddleware().
                 *
                 * gatherMiddleware() caches the computed list, so calling
                 * it before adding this middleware would prevent the newly
                 * added middleware from executing on this same request.
                 */
                if (
                    in_array(
                        $middleware,
                        $event
                            ->route
                            ->middleware(),
                        true
                    )
                ) {
                    return;
                }

                $event
                    ->route
                    ->middleware(
                        $middleware
                    );

                /*
                 * Laravel may already have calculated controller middleware.
                 * Clearing the public computed cache guarantees our newly
                 * attached middleware enters this request's final stack.
                 */
                $event
                    ->route
                    ->computedMiddleware =
                    null;
            }
        );
    }

    private function operationForRoute(
        ?string $routeName
    ): ?ShopBookingRuleOperation {
        return match ($routeName) {
            /*
             * Customer date + time availability.
             */
            'api.v1.customer.shops.services.available-dates',
            'api.v1.customer.shops.services.available-slots' =>
                ShopBookingRuleOperation::Availability,

            /*
             * New customer booking.
             */
            'api.v1.customer.appointments.store' =>
                ShopBookingRuleOperation::CustomerBooking,

            /*
             * Customer cancellation.
             */
            'api.v1.customer.appointments.cancel' =>
                ShopBookingRuleOperation::CustomerCancellation,

            /*
             * Customer asks to move an appointment.
             */
            'api.v1.customer.appointments.reschedule-requests.store' =>
                ShopBookingRuleOperation::CustomerRescheduleRequest,

            /*
             * Owner approving a pending move.
             */
            'api.v1.shop-owner.reschedule-requests.approve' =>
                ShopBookingRuleOperation::ShopOwnerRescheduleApproval,

            default =>
                null,
        };
    }
}