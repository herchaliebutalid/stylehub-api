<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\CancelAppointmentRequest;
use App\Http\Requests\Api\V1\ShopOwner\DeclineAppointmentRequest;
use App\Http\Resources\Api\V1\ShopOwnerAppointmentResource;
use App\Models\Shop;
use App\Services\Appointments\ShopOwnerAppointmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly ShopOwnerAppointmentWorkflowService $workflow
    ) {}

    /**
     * List appointments belonging to the shop owner's shop.
     *
     * Existing mobile requests retain their original response.
     *
     * Paginated requests:
     * ?page=1&per_page=20
     *
     * Optional status filtering:
     * ?page=1&per_page=20&status=pending
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'between:1,100',
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::enum(AppointmentStatus::class),
            ],
        ]);

        $shop = $this->authenticatedShop(
            $request
        );

        $query = $shop
            ->appointments()
            ->with([
                'customer',
                'service',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        /*
        |--------------------------------------------------------------------------
        | Backward Compatibility
        |--------------------------------------------------------------------------
        */

        if (! $request->hasAny([
            'page',
            'per_page',
            'status',
        ])) {
            return response()->json([
                'success' => true,
                'data' => [
                    'appointments' =>
                        ShopOwnerAppointmentResource::collection(
                            $query->get()
                        )->resolve($request),
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filtering
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
        | Pagination
        |--------------------------------------------------------------------------
        */

        $appointments = $query->paginate(
            (int) ($validated['per_page'] ?? 20)
        );

        /*
        |--------------------------------------------------------------------------
        | Accurate Shop Appointment Counts
        |--------------------------------------------------------------------------
        */

        $counts = $shop
            ->appointments()
            ->toBase()
            ->select('status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'total' => 0,
        ];

        foreach (AppointmentStatus::cases() as $status) {
            $summary[$status->value] = (int) (
                $counts[$status->value] ?? 0
            );

            $summary['total'] +=
                $summary[$status->value];
        }

        return response()->json([
            'success' => true,

            'data' => [
                'appointments' =>
                    ShopOwnerAppointmentResource::collection(
                        $appointments->getCollection()
                    )->resolve($request),

                'summary' => $summary,

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
     * View appointment details.
     */
    public function show(
        Request $request,
        string $appointment
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $ownedAppointment =
            $shop
                ->appointments()
                ->with([
                    'customer',
                    'service',
                ])
                ->whereKey(
                    $appointment
                )
                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'data' => [
                'appointment' => (
                    new ShopOwnerAppointmentResource(
                        $ownedAppointment
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Confirm a pending appointment.
     */
    public function confirm(
        Request $request,
        string $appointment
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $updated =
            $this->workflow->confirm(
                shop:
                    $shop,

                owner:
                    $request->user(),

                appointmentId:
                    (int) $appointment,
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Appointment confirmed successfully.',

            'data' => [
                'appointment' => (
                    new ShopOwnerAppointmentResource(
                        $updated
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Decline an appointment.
     */
    public function decline(
        DeclineAppointmentRequest $request,
        string $appointment
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $updated =
            $this->workflow->decline(
                shop:
                    $shop,

                owner:
                    $request->user(),

                appointmentId:
                    (int) $appointment,

                reason:
                    $request->validated(
                        'reason'
                    ),
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Appointment declined successfully.',

            'data' => [
                'appointment' => (
                    new ShopOwnerAppointmentResource(
                        $updated
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(
        CancelAppointmentRequest $request,
        string $appointment
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $updated =
            $this->workflow->cancel(
                shop:
                    $shop,

                owner:
                    $request->user(),

                appointmentId:
                    (int) $appointment,

                reason:
                    $request->validated(
                        'reason'
                    ),
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Appointment cancelled successfully.',

            'data' => [
                'appointment' => (
                    new ShopOwnerAppointmentResource(
                        $updated
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Complete an appointment.
     */
    public function complete(
        Request $request,
        string $appointment
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $updated =
            $this->workflow->complete(
                shop:
                    $shop,

                appointmentId:
                    (int) $appointment,
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Appointment completed successfully.',

            'data' => [
                'appointment' => (
                    new ShopOwnerAppointmentResource(
                        $updated
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Mark a confirmed appointment as No-show.
     */
    public function noShow(
        Request $request,
        string $appointment
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $updated =
            $this->workflow->markNoShow(
                shop:
                    $shop,

                appointmentId:
                    (int) $appointment,
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Appointment marked as no-show successfully.',

            'data' => [
                'appointment' => (
                    new ShopOwnerAppointmentResource(
                        $updated
                    )
                )->resolve(
                    $request
                ),
            ],
        ]);
    }

    /**
     * Return only the shop belonging to the authenticated owner.
     */
    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request
            ->user()
            ->shop()
            ->firstOrFail();
    }
}
