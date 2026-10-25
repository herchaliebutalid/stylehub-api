<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\BrowseAdminAppointmentsRequest;
use App\Http\Resources\Api\V1\AdminAppointmentResource;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAppointmentController extends Controller
{
    /**
     * Browse appointments across StyleHub.
     *
     * Administrator web only.
     * This endpoint is intentionally read-only.
     */
    public function index(
        BrowseAdminAppointmentsRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $query = $this->baseQuery();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        |
        | Search by:
        | - Customer name
        | - Customer email
        | - Shop name
        | - Service name
        |
        */

        if (isset($validated['search'])) {
            $search = $validated['search'];

            $query->where(
                function (Builder $query) use ($search): void {
                    $query
                        ->whereHas(
                            'customer',
                            function (Builder $customerQuery) use ($search): void {
                                $customerQuery
                                    ->withTrashed()
                                    ->where(
                                        function (Builder $customerQuery) use ($search): void {
                                            $customerQuery
                                                ->where(
                                                    'first_name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'middle_name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'last_name',
                                                    'like',
                                                    "%{$search}%"
                                                )
                                                ->orWhere(
                                                    'email',
                                                    'like',
                                                    "%{$search}%"
                                                );
                                        }
                                    );
                            }
                        )
                        ->orWhereHas(
                            'shop',
                            function (Builder $shopQuery) use ($search): void {
                                $shopQuery
                                    ->withTrashed()
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )
                        ->orWhereHas(
                            'service',
                            function (Builder $serviceQuery) use ($search): void {
                                $serviceQuery
                                    ->withTrashed()
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Appointment Status Filter
        |--------------------------------------------------------------------------
        */

        if (isset($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Shop Filter
        |--------------------------------------------------------------------------
        */

        if (isset($validated['shop_id'])) {
            $query->where(
                'shop_id',
                $validated['shop_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Customer Filter
        |--------------------------------------------------------------------------
        */

        if (isset($validated['customer_id'])) {
            $query->where(
                'customer_id',
                $validated['customer_id']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Appointment Date Range
        |--------------------------------------------------------------------------
        */

        if (isset($validated['date_from'])) {
            $query->whereDate(
                'appointment_date',
                '>=',
                $validated['date_from']
            );
        }

        if (isset($validated['date_to'])) {
            $query->whereDate(
                'appointment_date',
                '<=',
                $validated['date_to']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination and Sorting
        |--------------------------------------------------------------------------
        |
        | FIX:
        |
        | The appointments database table has:
        |   appointment_date
        |   starts_at
        |   ends_at
        |
        | It does NOT have a start_time column.
        |
        | Use starts_at when sorting appointments.
        |
        */

        $perPage = (int) (
            $validated['per_page'] ?? 20
        );

        $appointments = $query
            ->orderByDesc('appointment_date')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        /*
        |--------------------------------------------------------------------------
        | API Response
        |--------------------------------------------------------------------------
        |
        | Keep the existing response structure so the
        | Admin Vue application continues working.
        |
        */

        return response()->json([
            'success' => true,

            'data' => [
                'appointments' =>
                    AdminAppointmentResource::collection(
                        collect($appointments->items())
                    )->resolve($request),

                'pagination' => [
                    'current_page' =>
                        $appointments->currentPage(),

                    'per_page' =>
                        $appointments->perPage(),

                    'total' =>
                        $appointments->total(),

                    'last_page' =>
                        $appointments->lastPage(),

                    'has_more_pages' =>
                        $appointments->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * View the details of one appointment.
     *
     * Administrator web only.
     * Read-only endpoint.
     */
    public function show(
        Request $request,
        string $appointment
    ): JsonResponse {
        $appointmentModel = $this
            ->baseQuery()
            ->whereKey($appointment)
            ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'appointment' =>
                    (new AdminAppointmentResource(
                        $appointmentModel
                    ))->resolve($request),
            ],
        ]);
    }

    /**
     * Build the Admin appointment monitoring query.
     *
     * Historical customer, shop and service records
     * are included even when soft-deleted.
     *
     * Keep eager-loading callbacks without Builder
     * type hints because Laravel passes relationship
     * query objects into these callbacks.
     *
     * @return Builder<Appointment>
     */
    private function baseQuery(): Builder
    {
        return Appointment::query()
            ->with([
                'customer' => function ($query): void {
                    $query->withTrashed();
                },

                'shop' => function ($query): void {
                    $query->withTrashed();
                },

                'service' => function ($query): void {
                    $query->withTrashed();
                },
            ])
            ->withCount([
                'rescheduleRequests',
            ]);
    }
}
