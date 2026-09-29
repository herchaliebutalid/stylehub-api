<?php

namespace App\Http\Controllers\Api\V1\ShopOwner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShopOwner\CancelAppointmentRequest;
use App\Http\Requests\Api\V1\ShopOwner\DeclineAppointmentRequest;
use App\Http\Resources\Api\V1\ShopOwnerAppointmentResource;
use App\Models\Shop;
use App\Services\Appointments\ShopOwnerAppointmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly ShopOwnerAppointmentWorkflowService $workflow
    ) {}

    public function index(
        Request $request
    ): JsonResponse {
        $shop =
            $this->authenticatedShop(
                $request
            );

        $appointments =
            $shop
                ->appointments()
                ->with([
                    'customer',
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
            'success' =>
                true,

            'data' => [
                'appointments' =>
                    ShopOwnerAppointmentResource::collection(
                        $appointments
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }

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
     * Mark a confirmed appointment as No-show
     * after the appointment start time.
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

    private function authenticatedShop(
        Request $request
    ): Shop {
        return $request
            ->user()
            ->shop()
            ->firstOrFail();
    }
}