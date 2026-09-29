<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\GetShopOwnerCalendarMonthRequest;
use App\Http\Requests\Api\V1\ShopOwner\GetShopOwnerDayAgendaRequest;
use App\Http\Resources\Api\V1\ShopOwnerAppointmentResource;
use App\Models\Shop;
use App\Services\Calendar\ShopOwnerCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopOwnerCalendarController extends Controller
{
    public function __construct(
        private readonly ShopOwnerCalendarService $calendar
    ) {}

    /**
     * Return one month of calendar summary information.
     */
    public function month(
        GetShopOwnerCalendarMonthRequest $request
    ): JsonResponse {
        $timezone =
            (string) config(
                'app.timezone',
                'Asia/Manila'
            );

        $monthValue =
            $request->validated(
                'month'
            );

        $month =
            is_string(
                $monthValue
            )
                ? CarbonImmutable::createFromFormat(
                    '!Y-m',
                    $monthValue,
                    $timezone
                )
                : CarbonImmutable::now(
                    $timezone
                )->startOfMonth();

        return response()->json([
            'success' =>
                true,

            'data' =>
                $this
                    ->calendar
                    ->month(
                        $this->authenticatedShop(
                            $request
                        ),
                        $month
                    ),
        ]);
    }

    /**
     * Return the chronological agenda for one selected date.
     */
    public function agenda(
        GetShopOwnerDayAgendaRequest $request
    ): JsonResponse {
        $timezone =
            (string) config(
                'app.timezone',
                'Asia/Manila'
            );

        $date =
            CarbonImmutable::createFromFormat(
                '!Y-m-d',
                (string) $request->validated(
                    'date'
                ),
                $timezone
            );

        $data =
            $this
                ->calendar
                ->agenda(
                    $this->authenticatedShop(
                        $request
                    ),
                    $date
                );

        /*
        |--------------------------------------------------------------------------
        | Reuse Existing Secure Appointment Resource
        |--------------------------------------------------------------------------
        */

        $appointments =
            $data[
                'appointments'
            ];

        unset(
            $data[
                'appointments'
            ]
        );

        $data[
            'appointments'
        ] =
            ShopOwnerAppointmentResource::collection(
                $appointments
            )->resolve(
                $request
            );

        return response()->json([
            'success' =>
                true,

            'data' =>
                $data,
        ]);
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