<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CancelAppointmentRequest;
use App\Http\Requests\Api\V1\Customer\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\Customer\StoreRescheduleRequest;
use App\Http\Resources\Api\V1\AppointmentResource;
use App\Http\Resources\Api\V1\RescheduleRequestResource;
use App\Services\Appointments\AppointmentBookingService;
use App\Services\Appointments\CustomerAppointmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentBookingService $bookingService,
        private readonly CustomerAppointmentWorkflowService $workflow
    ) {}

    /**
     * List appointments for the authenticated customer.
     *
     * Legacy mobile requests without pagination parameters
     * continue to receive their complete appointment list.
     *
     * Paginated requests:
     * ?page=1&per_page=20
     *
     * Optional status filter:
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

        $query = $request->user()
            ->appointments()
            ->with([
                'shop',
                'service',
            ])
            ->orderByDesc('appointment_date')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');

        /*
        |--------------------------------------------------------------------------
        | Backward Compatibility
        |--------------------------------------------------------------------------
        |
        | Existing mobile screens still request the complete list.
        | Preserve that response until the mobile pagination
        | implementation is complete.
        |
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
                        AppointmentResource::collection(
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
        | Accurate Status Counts
        |--------------------------------------------------------------------------
        |
        | Counts are calculated from all of this customer's
        | appointments, not merely the current page.
        |
        */

        $counts = $request->user()
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
                    AppointmentResource::collection(
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
     * Create a customer appointment.
     */
    public function store(
        StoreAppointmentRequest $request
    ): JsonResponse {
        $appointment =
            $this->bookingService->book(
                customer:
                    $request->user(),

                data:
                    $request->validated(),
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Appointment submitted successfully.',

            'data' => [
                'appointment' => (
                    new AppointmentResource(
                        $appointment
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * View a customer's own appointment.
     */
    public function show(
        Request $request,
        string $appointment
    ): JsonResponse {
        $ownedAppointment =
            $request->user()
                ->appointments()
                ->with([
                    'shop',
                    'service',
                ])
                ->whereKey(
                    $appointment
                )
                ->firstOrFail();

        return response()->json([
            'success' => true,

            'data' => [
                'appointment' => (
                    new AppointmentResource(
                        $ownedAppointment
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Customer cancellation.
     */
    public function cancel(
        CancelAppointmentRequest $request,
        string $appointment
    ): JsonResponse {
        $updated =
            $this->workflow->cancel(
                customer:
                    $request->user(),

                appointmentId:
                    (int) $appointment,

                reason:
                    $request->validated(
                        'reason'
                    ),
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Appointment cancelled successfully.',

            'data' => [
                'appointment' => (
                    new AppointmentResource(
                        $updated
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Submit a reschedule request.
     *
     * The customer cannot directly change an appointment
     * protected by the shop-owner approval workflow.
     */
    public function requestReschedule(
        StoreRescheduleRequest $request,
        string $appointment
    ): JsonResponse {
        $rescheduleRequest =
            $this->workflow->requestReschedule(
                customer:
                    $request->user(),

                appointmentId:
                    (int) $appointment,

                data:
                    $request->validated(),
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Reschedule request submitted successfully.',

            'data' => [
                'reschedule_request' => (
                    new RescheduleRequestResource(
                        $rescheduleRequest
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }
}
