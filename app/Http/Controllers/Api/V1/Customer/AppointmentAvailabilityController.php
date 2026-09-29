<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\GetAvailableDatesRequest;
use App\Http\Requests\Api\V1\Customer\GetAvailableSlotsRequest;
use App\Services\Appointments\AppointmentAvailabilityService;
use App\Services\Appointments\ShopBlockedDateAvailabilityGuard;
use Illuminate\Http\JsonResponse;

class AppointmentAvailabilityController extends Controller
{
    public function __construct(
        private readonly AppointmentAvailabilityService $availabilityService,
        private readonly ShopBlockedDateAvailabilityGuard $blockedDateGuard
    ) {}

    /**
     * Return upcoming bookable dates for one shop service.
     */
    public function dates(
        GetAvailableDatesRequest $request,
        string $shop,
        string $service
    ): JsonResponse {
        $days =
            $request->validated(
                'days'
            )
            ??
            $this
                ->availabilityService
                ->defaultPreviewDays();

        /*
        |--------------------------------------------------------------------------
        | Existing Real Availability Engine
        |--------------------------------------------------------------------------
        */

        $availability =
            $this
                ->availabilityService
                ->availableDates(
                    customer:
                        $request->user(),

                    shopId:
                        (int) $shop,

                    serviceId:
                        (int) $service,

                    days:
                        (int) $days,
                );

        /*
        |--------------------------------------------------------------------------
        | Whole-Day Shop Closures
        |--------------------------------------------------------------------------
        */

        $availability =
            $this
                ->blockedDateGuard
                ->applyToAvailableDates(
                    shopId:
                        (int) $shop,

                    availability:
                        $availability,
                );

        return response()->json([
            'success' =>
                true,

            'data' =>
                $availability,
        ]);
    }

    /**
     * Return available appointment times for one specific date.
     */
    public function slots(
        GetAvailableSlotsRequest $request,
        string $shop,
        string $service
    ): JsonResponse {
        $date =
            (string) $request->validated(
                'date'
            );

        /*
        |--------------------------------------------------------------------------
        | Existing Real Slot Engine
        |--------------------------------------------------------------------------
        */

        $availability =
            $this
                ->availabilityService
                ->availableSlots(
                    customer:
                        $request->user(),

                    shopId:
                        (int) $shop,

                    serviceId:
                        (int) $service,

                    date:
                        $date,
                );

        /*
        |--------------------------------------------------------------------------
        | Whole-Day Shop Closure
        |--------------------------------------------------------------------------
        */

        $availability =
            $this
                ->blockedDateGuard
                ->applyToAvailableSlots(
                    shopId:
                        (int) $shop,

                    date:
                        $date,

                    availability:
                        $availability,
                );

        return response()->json([
            'success' =>
                true,

            'data' =>
                $availability,
        ]);
    }
}