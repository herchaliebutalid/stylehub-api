<?php

namespace App\Http\Controllers\Api\V1\Customer;

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
use Symfony\Component\HttpFoundation\Response;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentBookingService $bookingService,
        private readonly CustomerAppointmentWorkflowService $workflow
    ) {}

    public function index(
        Request $request
    ): JsonResponse {
        $appointments = $request->user()
            ->appointments()
            ->with([
                'shop',
                'service',
            ])
            ->orderByDesc(
                'appointment_date'
            )
            ->orderByDesc(
                'starts_at'
            )
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'appointments' =>
                    AppointmentResource::collection(
                        $appointments
                    )->resolve($request),
            ],
        ]);
    }

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
     * Submit a reschedule request instead of directly modifying
     * the protected appointment.
     */
    public function requestReschedule(
        StoreRescheduleRequest $request,
        string $appointment
    ): JsonResponse {
        $rescheduleRequest =
            $this->workflow
                ->requestReschedule(
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